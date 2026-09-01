(function () {
    const CATEGORY_ORDER = ['chat', 'call_analyse'];
    const AI_CHAT_DEMO_DAYS = 3;
    const AI_CHAT_DEMO_REQUEST_TYPES = ['connection', 'connection_extra_services'];
    const CATEGORY_LABELS = {
        chat: 'ИИ-Агент чатов',
        call_analyse: 'ИИ-Агент анализа звонков',
    };
    const CATEGORY_ALIASES = {
        call_analyse: 'call_analyse',
        'call-analyse': 'call_analyse',
        call_analysis: 'call_analyse',
        'call-analysis': 'call_analyse',
        calls: 'call_analyse',
        call: 'call_analyse',
        analyse: 'call_analyse',
        analysis: 'call_analyse',
        chat: 'chat',
        chats: 'chat',
    };

    function normalizeAiCategory(value) {
        const raw = String(value || '').trim().toLowerCase();
        if (!raw) {
            return 'chat';
        }
        return CATEGORY_ALIASES[raw] || raw;
    }

    function getAiCategoryLabel(category) {
        return CATEGORY_LABELS[category] || ('ИИ-Агент ' + category);
    }

    function getAiCategoryRules(category) {
        if (category === 'call_analyse') {
            return { showBalance: false, showPeriodDiscounts: false, showGifts: false, showDemo: false };
        }
        return { showBalance: true, showPeriodDiscounts: true, showGifts: true, showDemo: true };
    }

    function canOfferAiChatDemo(cp, category) {
        if (normalizeAiCategory(category) !== 'chat') {
            return false;
        }
        const type = String(cp?.state?.requestType || '').trim();
        return AI_CHAT_DEMO_REQUEST_TYPES.includes(type);
    }

    function calcAiDemoPrice(cp, unitPrice, days = AI_CHAT_DEMO_DAYS) {
        const price = Number(unitPrice) || 0;
        if (price <= 0 || days <= 0) {
            return 0;
        }
        return cp.roundMoney((price / 30) * days);
    }

    function mapAiItemFromPayload(raw, category) {
        if (!raw || !raw.plan_id) {
            return null;
        }
        return {
            plan_id: raw.plan_id,
            plan_name: raw.plan_name || '',
            category: normalizeAiCategory(raw.category || category),
            period_months: raw.period_months || 0,
            demo_days: raw.demo_days || 0,
            gift_months: raw.gift_months || 0,
            gift_original_price: raw.gift_original_price
                || ((Number(raw.unit_price) || 0) * (Number(raw.gift_months) || 0))
                || 0,
            unit_price: raw.unit_price || 0,
            discount_percent: raw.discount_percent || 0,
            partner_percent: raw.partner_percent || 0,
            original_price: raw.original_price || 0,
            total_price: raw.total_price || 0,
            current_month_amount: raw.current_month_amount || 0,
            balance_topup: raw.balance_topup || 0,
            currency: raw.currency || '',
        };
    }

    function attachAiCategoryHelpers(cp) {
        cp.normalizeAiCategory = normalizeAiCategory;
        cp.getAiCategoryLabel = getAiCategoryLabel;
        cp.getAiCategoryRules = getAiCategoryRules;

        cp.getAiCategoryOrder = function () {
            const seen = new Set();
            const order = [];
            CATEGORY_ORDER.forEach((key) => {
                seen.add(key);
                order.push(key);
            });
            (this.aiTariffPlans || []).forEach((plan) => {
                const key = normalizeAiCategory(plan.category);
                if (!seen.has(key)) {
                    seen.add(key);
                    order.push(key);
                }
            });
            return order;
        };

        cp.getSelectedAiItems = function () {
            const items = this.state.aiItems || {};
            return this.getAiCategoryOrder()
                .map((category) => items[category])
                .filter((item) => item && item.plan_id);
        };

        cp.syncPrimaryAiItem = function () {
            const selected = this.getSelectedAiItems();
            this.state.aiItem = selected[0] || null;
        };

        cp.getAiChargeTotal = function (aiItem) {
            if (aiItem === undefined) {
                return this.roundMoney(
                    this.getSelectedAiItems().reduce((sum, item) => sum + this.getAiChargeTotal(item), 0)
                );
            }
            if (!aiItem) {
                return 0;
            }
            return this.roundMoney(
                (Number(aiItem.current_month_amount) || 0)
                + (Number(aiItem.total_price) || 0)
                + (Number(aiItem.balance_topup) || 0)
            );
        };

        cp.buildAiPaymentLines = function (aiItem) {
            if (!aiItem || this.getAiChargeTotal(aiItem) <= 0 && !aiItem.is_ai_gift) {
                if (!aiItem) return [];
            }
            const lines = [];
            const label = this.getAiCategoryLabel(aiItem.category);
            const demoDays = Number(aiItem.demo_days) || 0;
            if (demoDays > 0 && (Number(aiItem.total_price) || 0) > 0) {
                lines.push({
                    service_key: `ai-demo-${aiItem.plan_id}`,
                    name: `${label} «${aiItem.plan_name || ''}» — демо ${demoDays} дня`,
                    quantity: 1,
                    pricing_kind: 'pack',
                    unit_price: this.roundMoney(aiItem.total_price || 0),
                    price: this.roundMoney(aiItem.total_price || 0),
                    is_ai_agent: true,
                    is_ai_demo: true,
                    ai_category: aiItem.category,
                });
            }
            if (demoDays <= 0 && (Number(aiItem.current_month_amount) || 0) > 0) {
                lines.push({
                    service_key: `ai-current-month-${aiItem.plan_id}`,
                    name: `${label} «${aiItem.plan_name || ''}» — текущий месяц`,
                    quantity: 1,
                    pricing_kind: 'pack',
                    unit_price: this.roundMoney(aiItem.current_month_amount || 0),
                    price: this.roundMoney(aiItem.current_month_amount || 0),
                    is_ai_agent: true,
                    is_ai_current_month: true,
                    ai_category: aiItem.category,
                });
            }
            if ((Number(aiItem.total_price) || 0) > 0 && (Number(aiItem.period_months) || 0) > 0) {
                lines.push({
                    service_key: `ai-plan-${aiItem.plan_id}`,
                    name: `${label} «${aiItem.plan_name || ''}» (+${aiItem.period_months} мес)`,
                    quantity: 1,
                    pricing_kind: 'pack',
                    unit_price: this.roundMoney(aiItem.unit_price || 0),
                    price: this.roundMoney(aiItem.total_price || 0),
                    is_ai_agent: true,
                    ai_category: aiItem.category,
                });
            }
            const rules = getAiCategoryRules(aiItem.category);
            const giftMonths = rules.showGifts
                ? (Number(aiItem.gift_months) || this.getAiGiftMonthsForPeriod(aiItem.period_months))
                : 0;
            const giftOriginal = this.roundMoney(
                aiItem.gift_original_price
                ?? ((Number(aiItem.unit_price) || 0) * giftMonths)
            );
            if (giftMonths > 0 && giftOriginal > 0) {
                lines.push({
                    service_key: `ai-plan-gift-${aiItem.plan_id}`,
                    name: `${label} «${aiItem.plan_name || ''}» (+${giftMonths} мес в подарок)`,
                    quantity: 1,
                    pricing_kind: 'pack',
                    unit_price: this.roundMoney(aiItem.unit_price || 0),
                    price: 0,
                    original_price: giftOriginal,
                    discount_percent: 100,
                    is_ai_agent: true,
                    is_ai_gift: true,
                    ai_category: aiItem.category,
                });
            }
            if (rules.showBalance && (Number(aiItem.balance_topup) || 0) > 0) {
                lines.push({
                    service_key: `ai-balance-topup-${aiItem.plan_id}`,
                    name: `${label} — баланс ИИ`,
                    quantity: 1,
                    pricing_kind: 'pack',
                    unit_price: this.roundMoney(aiItem.balance_topup || 0),
                    price: this.roundMoney(aiItem.balance_topup || 0),
                    is_ai_agent: true,
                    is_ai_balance_topup: true,
                    ai_category: aiItem.category,
                });
            }
            return lines.filter((line) => (Number(line.price) || 0) > 0 || line.is_ai_gift);
        };

        cp.appendSelectedAiPaymentItems = function (items) {
            this.getSelectedAiItems().forEach((aiItem) => {
                this.buildAiPaymentLines(aiItem).forEach((line) => items.push(line));
            });
            return items;
        };

        cp.buildAiOfferPayload = function () {
            const items = this.getSelectedAiItems();
            return {
                ai_item: items[0] || null,
                ai_items: items,
            };
        };

        cp.collectAiSummaryTotals = function () {
            const selected = this.getSelectedAiItems();
            let aiSubTotal = 0;
            let aiCurrentMonth = 0;
            let aiTopup = 0;
            let aiGiftOriginal = 0;
            let aiPaidOriginal = 0;
            let aiPartnerShare = 0;
            selected.forEach((aiItem) => {
                const rules = getAiCategoryRules(aiItem.category);
                const subTotal = this.roundMoney(aiItem.total_price || 0);
                const currentMonth = this.roundMoney(aiItem.current_month_amount || 0);
                const topup = rules.showBalance ? this.roundMoney(aiItem.balance_topup || 0) : 0;
                const giftMonths = rules.showGifts
                    ? (Number(aiItem.gift_months) || this.getAiGiftMonthsForPeriod(aiItem.period_months))
                    : 0;
                const giftOriginal = this.roundMoney(
                    aiItem.gift_original_price
                    ?? ((Number(aiItem.unit_price) || 0) * giftMonths)
                );
                const paidOriginal = this.roundMoney(aiItem.original_price || 0);
                const total = this.roundMoney(currentMonth + subTotal + topup);
                const partnerPct = Number(aiItem.partner_percent) || 0;
                aiSubTotal += subTotal;
                aiCurrentMonth += currentMonth;
                aiTopup += topup;
                aiGiftOriginal += giftOriginal;
                aiPaidOriginal += paidOriginal;
                aiPartnerShare += this.roundMoney(total * (partnerPct / 100));
            });
            const aiTotal = this.roundMoney(aiCurrentMonth + aiSubTotal + aiTopup);
            const aiPaidDiscount = this.roundMoney(aiPaidOriginal - aiSubTotal);
            const aiOriginal = this.roundMoney(aiCurrentMonth + aiPaidOriginal + aiGiftOriginal + aiTopup);
            const aiDiscount = this.roundMoney(aiPaidDiscount + aiGiftOriginal);
            return {
                selected,
                hasAi: selected.length > 0,
                aiSubTotal,
                aiCurrentMonth,
                aiTopup,
                aiGiftOriginal,
                aiPaidOriginal,
                aiPaidDiscount,
                aiTotal,
                aiOriginal,
                aiDiscount,
                aiPartnerShare,
            };
        };

        cp.renderAiPaymentTableRows = function (totals) {
            if (!totals.hasAi) {
                return '';
            }
            let html = '';
            totals.selected.forEach((aiItem) => {
                const rules = getAiCategoryRules(aiItem.category);
                const label = this.getAiCategoryLabel(aiItem.category);
                const partnerPct = Number(aiItem.partner_percent) || 0;
                const currentMonth = this.roundMoney(aiItem.current_month_amount || 0);
                const subTotal = this.roundMoney(aiItem.total_price || 0);
                const paidOriginal = this.roundMoney(aiItem.original_price || 0);
                const paidDiscount = this.roundMoney(paidOriginal - subTotal);
                const months = Number(aiItem.period_months) || 0;
                const demoDays = Number(aiItem.demo_days) || 0;
                const giftMonths = rules.showGifts
                    ? (Number(aiItem.gift_months) || this.getAiGiftMonthsForPeriod(aiItem.period_months))
                    : 0;
                const giftOriginal = this.roundMoney(
                    aiItem.gift_original_price
                    ?? ((Number(aiItem.unit_price) || 0) * giftMonths)
                );
                const topup = rules.showBalance ? this.roundMoney(aiItem.balance_topup || 0) : 0;

                html += `<tr><th colspan="9" class="section-header">${label}</th></tr>`;
                if (demoDays > 0 && subTotal > 0) {
                    const share = this.roundMoney(subTotal * (partnerPct / 100));
                    html += `
                    <tr>
                        <td>${label} «${aiItem.plan_name || ''}» — демо ${demoDays} дня</td>
                        <td>1</td>
                        <td>${this.formatServicePrice(subTotal)}</td>
                        <td>демо</td>
                        <td>${this.formatTotalPrice(subTotal)}</td>
                        <td>${this.formatTotalPrice(0)} (0%)</td>
                        <td>${this.formatTotalPrice(subTotal)}</td>
                        <td>${partnerPct}%</td>
                        <td>${this.formatTotalPrice(share)}</td>
                    </tr>`;
                }
                if (demoDays <= 0 && currentMonth > 0) {
                    const share = this.roundMoney(currentMonth * (partnerPct / 100));
                    html += `
                    <tr>
                        <td>${label} «${aiItem.plan_name || ''}» — текущий месяц</td>
                        <td>1</td>
                        <td>${this.formatServicePrice(currentMonth)}</td>
                        <td>—</td>
                        <td>${this.formatTotalPrice(currentMonth)}</td>
                        <td>${this.formatTotalPrice(0)} (0%)</td>
                        <td>${this.formatTotalPrice(currentMonth)}</td>
                        <td>${partnerPct}%</td>
                        <td>${this.formatTotalPrice(share)}</td>
                    </tr>`;
                }
                if (months > 0) {
                    const share = this.roundMoney(subTotal * (partnerPct / 100));
                    html += `
                    <tr>
                        <td>${label} «${aiItem.plan_name || ''}» (+${months} мес)</td>
                        <td>1</td>
                        <td>${this.formatServicePrice(aiItem.unit_price)}</td>
                        <td>+${months}</td>
                        <td>${this.formatTotalPrice(paidOriginal)}</td>
                        <td>${this.formatTotalPrice(paidDiscount)} (${aiItem.discount_percent || 0}%)</td>
                        <td>${this.formatTotalPrice(subTotal)}</td>
                        <td>${partnerPct}%</td>
                        <td>${this.formatTotalPrice(share)}</td>
                    </tr>`;
                }
                if (giftMonths > 0) {
                    html += `
                    <tr>
                        <td>${label} «${aiItem.plan_name || ''}» (+${giftMonths} мес в подарок)</td>
                        <td>1</td>
                        <td>${this.formatServicePrice(aiItem.unit_price)}</td>
                        <td>+${giftMonths}</td>
                        <td>${this.formatTotalPrice(giftOriginal)}</td>
                        <td>${this.formatTotalPrice(giftOriginal)} (100%)</td>
                        <td>${this.formatTotalPrice(0)}</td>
                        <td>${partnerPct}%</td>
                        <td>${this.formatTotalPrice(0)}</td>
                    </tr>`;
                }
                if (topup > 0) {
                    const share = this.roundMoney(topup * (partnerPct / 100));
                    html += `
                    <tr>
                        <td>${label} — баланс ИИ</td>
                        <td>1</td>
                        <td>${this.formatServicePrice(topup)}</td>
                        <td>—</td>
                        <td>${this.formatTotalPrice(topup)}</td>
                        <td>${this.formatTotalPrice(0)} (0%)</td>
                        <td>${this.formatTotalPrice(topup)}</td>
                        <td>${partnerPct}%</td>
                        <td>${this.formatTotalPrice(share)}</td>
                    </tr>`;
                }
            });
            return html;
        };
    }

    function initAiAgentBlock(cp) {
        attachAiCategoryHelpers(cp);
        if (!cp.state.aiItems || typeof cp.state.aiItems !== 'object') {
            cp.state.aiItems = {};
        }

        const plans = cp.aiTariffPlans || [];
        const section = document.getElementById('aiAgentSection');
        if (!section) {
            return;
        }

        let root = document.getElementById('aiCategoriesRoot');
        if (!root) {
            root = document.createElement('div');
            root.id = 'aiCategoriesRoot';
            section.innerHTML = '';
            section.appendChild(root);
        }

        section.dataset.aiReady = plans.length > 0 ? '1' : '0';
        root.innerHTML = '';
        cp._aiCategoryUi = {};

        if (!plans.length) {
            cp.syncAiAgentAvailability();
            return;
        }

        const getAiPrice = (plan) => {
            const cur = cp.normalizeCurrencyCode(cp.state.currency) || '';
            const byC = plan?.prices_by_currency || {};
            if (!cur) return 0;
            const direct = byC[cur] ?? byC[cur.toLowerCase()] ?? byC[cur.toUpperCase()];
            const amount = Number(direct);
            return Number.isFinite(amount) && amount > 0 ? amount : 0;
        };
        const getAiCurrency = () => cp.normalizeCurrencyCode(cp.state.currency) || '';

        const plansByCategory = new Map();
        plans.forEach((plan) => {
            const category = normalizeAiCategory(plan.category);
            if (!plansByCategory.has(category)) {
                plansByCategory.set(category, []);
            }
            plansByCategory.get(category).push(plan);
        });

        const renderCategory = (category, categoryPlans) => {
            const rules = getAiCategoryRules(category);
            const block = document.createElement('div');
            block.className = 'ai-category-block';
            block.dataset.category = category;
            block.innerHTML = `
                <div class="section-header-with-button" style="margin-bottom:12px;">
                    <h2 class="section-title" style="margin:0;">${getAiCategoryLabel(category)}</h2>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;color:#374151;">
                        <input type="checkbox" class="ai-enabled-checkbox" style="width:16px;height:16px;accent-color:#2B4BFF;">
                        Подключить
                    </label>
                </div>
                <div class="ai-category-body" style="display:none;">
                    <div class="ai-plans-by-model ai-plans-grid"></div>
                    <div class="ai-period-row" style="display:none;margin-top:20px;">
                        <div class="setting-group">
                            <label class="setting-label">${canOfferAiChatDemo(cp, category) ? 'Период (необязательно)' : 'Доп. месяцы (необязательно)'}</label>
                            <div class="period-selector ai-period-selector"></div>
                        </div>
                    </div>
                    <div class="ai-current-month-row" style="display:none;margin-top:20px;">
                        <div class="setting-group">
                            <label class="setting-label">Текущий месяц</label>
                            <p style="font-size:12px;color:#6b7280;margin:0 0 8px;line-height:1.4;">
                                Пропорция за оставшиеся дни текущего месяца.
                            </p>
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <input type="number" class="ai-current-month-input" min="0" step="0.01" value="0" readonly
                                       style="width:160px;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#f3f4f6;color:#111827;">
                                <span class="ai-current-month-currency" style="font-size:14px;color:#374151;font-weight:600;"></span>
                            </div>
                        </div>
                    </div>
                    ${rules.showBalance ? `
                    <div class="ai-balance-topup-row" style="display:none;margin-top:20px;">
                        <div class="setting-group">
                            <label class="setting-label">Баланс ИИ</label>
                            <p style="font-size:12px;color:#6b7280;margin:0 0 8px;line-height:1.4;">
                                Произвольное пополнение кошелька ИИ (необязательно).
                            </p>
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                <input type="number" class="ai-balance-topup-input" min="0" step="0.01" value=""
                                       placeholder="0"
                                       style="width:160px;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;">
                                <span class="ai-balance-topup-currency" style="font-size:14px;color:#374151;font-weight:600;"></span>
                            </div>
                        </div>
                    </div>` : ''}
                </div>
            `;
            root.appendChild(block);

            const ui = {
                category,
                rules,
                plans: categoryPlans,
                selectedPlanId: null,
                selectedMonths: null,
                selectedDemoDays: null,
                checkbox: block.querySelector('.ai-enabled-checkbox'),
                body: block.querySelector('.ai-category-body'),
                plansGrid: block.querySelector('.ai-plans-grid'),
                periodRow: block.querySelector('.ai-period-row'),
                periodSelector: block.querySelector('.ai-period-selector'),
                currentMonthRow: block.querySelector('.ai-current-month-row'),
                currentMonthInput: block.querySelector('.ai-current-month-input'),
                currentMonthCurrency: block.querySelector('.ai-current-month-currency'),
                topupRow: block.querySelector('.ai-balance-topup-row'),
                topupInput: block.querySelector('.ai-balance-topup-input'),
                topupCurrency: block.querySelector('.ai-balance-topup-currency'),
            };
            cp._aiCategoryUi[category] = ui;

            const syncCurrentAndBalanceUi = (unitPrice, currency) => {
                const current = cp.suggestAiBalanceTopup(unitPrice);
                if (ui.currentMonthRow) ui.currentMonthRow.style.display = 'block';
                if (ui.topupRow) ui.topupRow.style.display = 'block';
                if (ui.currentMonthCurrency) ui.currentMonthCurrency.textContent = currency || '';
                if (ui.topupCurrency) ui.topupCurrency.textContent = currency || '';
                if (ui.currentMonthInput) ui.currentMonthInput.value = current;
                if (ui.periodRow) ui.periodRow.style.display = 'block';
                return current;
            };

            const readTopup = () => {
                if (!rules.showBalance || !ui.topupInput) return 0;
                const value = Number(ui.topupInput.value);
                return Number.isFinite(value) && value > 0 ? cp.roundMoney(value) : 0;
            };

            const applyAiItem = (options = {}) => {
                const refreshSummary = options.refreshSummary !== false;
                if (!cp.isAiAgentAllowedForSelectedTariff()) {
                    ui.checkbox.checked = false;
                    ui.body.style.display = 'none';
                    if (ui.currentMonthRow) ui.currentMonthRow.style.display = 'none';
                    if (ui.topupRow) ui.topupRow.style.display = 'none';
                    cp.state.aiItems[category] = null;
                    cp.syncPrimaryAiItem();
                    if (refreshSummary) cp.updateSummary();
                    return;
                }
                if (ui.checkbox.checked && ui.selectedPlanId) {
                    const plan = categoryPlans.find((item) => item.id === ui.selectedPlanId);
                    const unitPrice = getAiPrice(plan);
                    const currency = getAiCurrency();
                    const partnerPct = typeof cp.getPartnerPackPercent === 'function'
                        ? cp.getPartnerPackPercent() : 0;
                    const topup = readTopup();
                    const months = ui.selectedMonths ? Number(ui.selectedMonths) : 0;
                    const demoDays = ui.selectedDemoDays ? Number(ui.selectedDemoDays) : 0;
                    const isDemo = demoDays > 0;
                    let currentMonth = 0;
                    if (isDemo) {
                        if (ui.currentMonthRow) ui.currentMonthRow.style.display = 'none';
                        if (ui.topupRow) ui.topupRow.style.display = rules.showBalance ? 'block' : 'none';
                        if (ui.periodRow) ui.periodRow.style.display = 'block';
                        if (ui.currentMonthInput) ui.currentMonthInput.value = 0;
                        if (ui.topupCurrency) ui.topupCurrency.textContent = currency || '';
                    } else {
                        currentMonth = syncCurrentAndBalanceUi(unitPrice, currency);
                    }
                    const giftMonths = (!isDemo && rules.showGifts && months > 0)
                        ? cp.getAiGiftMonthsForPeriod(months)
                        : 0;
                    let discountPct = 0;
                    let original = 0;
                    let total = 0;
                    if (isDemo) {
                        original = calcAiDemoPrice(cp, unitPrice, demoDays);
                        total = original;
                    } else if (months > 0) {
                        const period = (plan?.periods || []).find((item) => Number(item.months) === months);
                        discountPct = rules.showPeriodDiscounts ? (period?.discount_percent ?? 0) : 0;
                        original = cp.roundMoney(unitPrice * months);
                        total = cp.roundMoney(original * (1 - discountPct / 100));
                    }
                    cp.state.aiItems[category] = {
                        plan_id: ui.selectedPlanId,
                        plan_name: plan.name,
                        category,
                        period_months: isDemo ? 0 : months,
                        demo_days: isDemo ? demoDays : 0,
                        gift_months: giftMonths,
                        gift_original_price: giftMonths > 0 ? cp.roundMoney(unitPrice * giftMonths) : 0,
                        unit_price: unitPrice,
                        discount_percent: discountPct,
                        partner_percent: partnerPct,
                        original_price: original,
                        total_price: total,
                        current_month_amount: currentMonth,
                        balance_topup: topup,
                        currency,
                    };
                } else {
                    cp.state.aiItems[category] = null;
                    if (ui.currentMonthRow) ui.currentMonthRow.style.display = 'none';
                    if (ui.topupRow) ui.topupRow.style.display = 'none';
                }
                cp.syncPrimaryAiItem();
                if (refreshSummary) {
                    cp.updateSummary();
                }
            };

            const renderPeriods = (plan) => {
                ui.periodSelector.innerHTML = '';
                const hasDemo = canOfferAiChatDemo(cp, category);
                if ((!plan.periods || !plan.periods.length) && !hasDemo) {
                    ui.periodRow.style.display = 'none';
                    return;
                }
                ui.periodRow.style.display = 'block';
                if (hasDemo) {
                    const demoBtn = document.createElement('button');
                    demoBtn.type = 'button';
                    demoBtn.className = 'period-btn';
                    demoBtn.dataset.demoDays = String(AI_CHAT_DEMO_DAYS);
                    demoBtn.innerHTML = 'Демо ' + AI_CHAT_DEMO_DAYS + ' дня';
                    demoBtn.addEventListener('click', () => {
                        const already = demoBtn.classList.contains('active');
                        ui.periodSelector.querySelectorAll('.period-btn').forEach((item) => item.classList.remove('active'));
                        if (already) {
                            ui.selectedDemoDays = null;
                            ui.selectedMonths = null;
                        } else {
                            demoBtn.classList.add('active');
                            ui.selectedDemoDays = AI_CHAT_DEMO_DAYS;
                            ui.selectedMonths = null;
                        }
                        applyAiItem();
                    });
                    ui.periodSelector.appendChild(demoBtn);
                }
                (plan.periods || []).forEach((period) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'period-btn';
                    btn.dataset.months = period.months;
                    const giftMonths = rules.showGifts ? cp.getAiGiftMonthsForPeriod(period.months) : 0;
                    let label = '+' + period.months + ' мес';
                    if (rules.showPeriodDiscounts && period.discount_percent > 0) {
                        label += ' <span class="discount-badge">-' + period.discount_percent + '%</span>';
                    }
                    if (giftMonths > 0) {
                        label += ' <span class="gift-badge">+' + giftMonths + ' подарок</span>';
                    }
                    btn.innerHTML = label;
                    btn.addEventListener('click', () => {
                        const already = btn.classList.contains('active');
                        ui.periodSelector.querySelectorAll('.period-btn').forEach((item) => item.classList.remove('active'));
                        if (already) {
                            ui.selectedMonths = null;
                            ui.selectedDemoDays = null;
                        } else {
                            btn.classList.add('active');
                            ui.selectedMonths = period.months;
                            ui.selectedDemoDays = null;
                        }
                        applyAiItem();
                    });
                    ui.periodSelector.appendChild(btn);
                });
            };

            const renderCardPrice = (card, plan) => {
                const bestPeriod = (plan.periods || []).slice().sort((a, b) => b.discount_percent - a.discount_percent)[0];
                const discountHtml = rules.showPeriodDiscounts && bestPeriod && bestPeriod.discount_percent > 0
                    ? `<span class="discount-badge" style="margin-left:6px;">-${bestPeriod.discount_percent}%</span>` : '';
                const displayPrice = getAiPrice(plan);
                const displayCur = getAiCurrency();
                const priceEl = card.querySelector('.ai-price-area');
                if (priceEl) {
                    priceEl.innerHTML = displayPrice > 0
                        ? `<div class="tariff-price"><span class="price-value">${displayPrice}</span><span class="price-period">&nbsp;${displayCur}/мес${discountHtml}</span></div>`
                        : `<div style="font-size:12px;color:#9ca3af;margin-top:4px;">Цена не задана</div>`;
                }
            };

            ui.plansGrid.innerHTML = '';
            ui.plansGrid.classList.add('ai-plans-by-model');

            const modelGroups = new Map();
            categoryPlans.forEach((plan) => {
                const modelKey = String(plan.model_name || '').trim() || 'Прочие';
                if (!modelGroups.has(modelKey)) {
                    modelGroups.set(modelKey, []);
                }
                modelGroups.get(modelKey).push(plan);
            });

            const sortedModelNames = Array.from(modelGroups.keys()).sort((a, b) => {
                if (a === 'Прочие') return 1;
                if (b === 'Прочие') return -1;
                return a.localeCompare(b, 'ru', { sensitivity: 'base' });
            });

            sortedModelNames.forEach((modelName) => {
                const groupPlans = modelGroups.get(modelName).slice().sort((a, b) => {
                    const priceDiff = getAiPrice(a) - getAiPrice(b);
                    if (priceDiff !== 0) return priceDiff;
                    return String(a.name || '').localeCompare(String(b.name || ''), 'ru', { sensitivity: 'base' });
                });

                const groupEl = document.createElement('div');
                groupEl.className = 'ai-model-group';

                const titleEl = document.createElement('div');
                titleEl.className = 'ai-model-group-title';
                titleEl.textContent = modelName;
                groupEl.appendChild(titleEl);

                const rowEl = document.createElement('div');
                rowEl.className = 'ai-model-plans-row';

                groupPlans.forEach((plan) => {
                    const card = document.createElement('div');
                    card.className = 'tariff-card';
                    card.dataset.planId = plan.id;
                    const bestPeriod = (plan.periods || []).slice().sort((a, b) => b.discount_percent - a.discount_percent)[0];
                    const discountHtml = rules.showPeriodDiscounts && bestPeriod && bestPeriod.discount_percent > 0
                        ? `<span class="discount-badge" style="margin-left:6px;">-${bestPeriod.discount_percent}%</span>` : '';
                    const displayPrice = getAiPrice(plan);
                    const displayCur = getAiCurrency();
                    const priceDisplay = displayPrice > 0
                        ? `<div class="tariff-price"><span class="price-value">${displayPrice}</span><span class="price-period">&nbsp;${displayCur}/мес${discountHtml}</span></div>`
                        : `<div style="font-size:12px;color:#9ca3af;margin-top:4px;">Цена не задана</div>`;
                    card.innerHTML = `
                        <div class="tariff-select-indicator"></div>
                        <div class="tariff-name" style="margin-top:24px;">${plan.name}</div>
                        <div class="ai-price-area">${priceDisplay}</div>`;
                    card.addEventListener('click', () => {
                        ui.plansGrid.querySelectorAll('.tariff-card').forEach((item) => item.classList.remove('selected'));
                        card.classList.add('selected');
                        ui.selectedPlanId = plan.id;
                        ui.selectedMonths = null;
                        ui.selectedDemoDays = null;
                        renderPeriods(plan);
                        applyAiItem();
                    });
                    rowEl.appendChild(card);
                });

                groupEl.appendChild(rowEl);
                ui.plansGrid.appendChild(groupEl);
            });

            if (ui.topupInput) {
                ui.topupInput.addEventListener('input', () => applyAiItem());
            }

            ui.checkbox.addEventListener('change', () => {
                if (!cp.isAiAgentAllowedForSelectedTariff()) {
                    ui.checkbox.checked = false;
                    ui.body.style.display = 'none';
                    cp.state.aiItems[category] = null;
                    cp.syncPrimaryAiItem();
                    cp.syncAiAgentAvailability({ refreshSummary: true });
                    return;
                }
                ui.body.style.display = ui.checkbox.checked ? 'block' : 'none';
                if (!ui.checkbox.checked) {
                    ui.selectedPlanId = null;
                    ui.selectedMonths = null;
                    ui.selectedDemoDays = null;
                    ui.periodRow.style.display = 'none';
                    if (ui.currentMonthRow) ui.currentMonthRow.style.display = 'none';
                    if (ui.topupRow) ui.topupRow.style.display = 'none';
                    ui.plansGrid.querySelectorAll('.tariff-card').forEach((item) => item.classList.remove('selected'));
                    cp.state.aiItems[category] = null;
                    cp.syncPrimaryAiItem();
                    cp.updateSummary();
                }
            });

            ui.applyAiItem = applyAiItem;
            ui.renderPeriods = renderPeriods;
            ui.renderCardPrice = renderCardPrice;
            ui.getAiPrice = getAiPrice;
            ui.syncCurrentAndBalanceUi = syncCurrentAndBalanceUi;
        };

        cp.getAiCategoryOrder().forEach((category) => {
            const categoryPlans = plansByCategory.get(category);
            if (categoryPlans && categoryPlans.length) {
                renderCategory(category, categoryPlans);
            }
        });

        cp._refreshAiGiftUi = (options = {}) => {
            Object.values(cp._aiCategoryUi || {}).forEach((ui) => {
                if (!ui.rules.showGifts || !ui.checkbox.checked || !ui.selectedPlanId) {
                    return;
                }
                const plan = ui.plans.find((item) => item.id === ui.selectedPlanId);
                if (!plan) {
                    return;
                }
                const keepMonths = ui.selectedMonths;
                const keepDemoDays = ui.selectedDemoDays;
                ui.renderPeriods(plan);
                if (keepDemoDays) {
                    const targetBtn = ui.periodSelector.querySelector(`[data-demo-days="${keepDemoDays}"]`);
                    if (targetBtn) {
                        ui.periodSelector.querySelectorAll('.period-btn').forEach((item) => item.classList.remove('active'));
                        targetBtn.classList.add('active');
                        ui.selectedDemoDays = keepDemoDays;
                        ui.selectedMonths = null;
                    }
                } else if (keepMonths) {
                    const targetBtn = ui.periodSelector.querySelector(`[data-months="${keepMonths}"]`);
                    if (targetBtn) {
                        ui.periodSelector.querySelectorAll('.period-btn').forEach((item) => item.classList.remove('active'));
                        targetBtn.classList.add('active');
                        ui.selectedMonths = keepMonths;
                    }
                }
                ui.applyAiItem({ refreshSummary: options.refreshSummary !== false });
            });
        };

        cp._refreshAiCardPrices = () => {
            Object.values(cp._aiCategoryUi || {}).forEach((ui) => {
                ui.plansGrid.querySelectorAll('.tariff-card').forEach((card) => {
                    const plan = ui.plans.find((item) => String(item.id) === String(card.dataset.planId));
                    if (plan) {
                        ui.renderCardPrice(card, plan);
                    }
                });
                if (ui.checkbox.checked && ui.selectedPlanId) {
                    const plan = ui.plans.find((item) => item.id === ui.selectedPlanId);
                    if (plan && !ui.selectedDemoDays) {
                        ui.syncCurrentAndBalanceUi(ui.getAiPrice(plan), getAiCurrency());
                    }
                    ui.applyAiItem({ refreshSummary: false });
                }
            });
        };

        const pendingItems = [];
        if (Array.isArray(cp._pendingAiItems) && cp._pendingAiItems.length) {
            pendingItems.push(...cp._pendingAiItems);
        } else if (cp._pendingAiItem && cp._pendingAiItem.plan_id) {
            pendingItems.push(cp._pendingAiItem);
        }

        pendingItems.forEach((pending) => {
            const plan = plans.find((item) => item.id === pending.plan_id);
            if (!plan || !cp.isAiAgentAllowedForSelectedTariff()) {
                return;
            }
            const category = normalizeAiCategory(pending.category || plan.category);
            const ui = cp._aiCategoryUi[category];
            if (!ui) {
                return;
            }
            ui.checkbox.checked = true;
            ui.body.style.display = 'block';
            const targetCard = ui.plansGrid.querySelector(`[data-plan-id="${plan.id}"]`);
            if (targetCard) {
                ui.plansGrid.querySelectorAll('.tariff-card').forEach((item) => item.classList.remove('selected'));
                targetCard.classList.add('selected');
            }
            ui.selectedPlanId = plan.id;
            ui.selectedMonths = null;
            ui.selectedDemoDays = null;
            ui.renderPeriods(plan);
            const pendingDemoDays = Number(pending.demo_days) || 0;
            const pendingMonths = Number(pending.period_months) || 0;
            if (pendingDemoDays > 0) {
                const targetBtn = ui.periodSelector.querySelector(`[data-demo-days="${pendingDemoDays}"]`);
                if (targetBtn) {
                    ui.periodSelector.querySelectorAll('.period-btn').forEach((item) => item.classList.remove('active'));
                    targetBtn.classList.add('active');
                    ui.selectedDemoDays = pendingDemoDays;
                    ui.selectedMonths = null;
                }
            } else if (pendingMonths > 0) {
                const targetBtn = ui.periodSelector.querySelector(`[data-months="${pendingMonths}"]`);
                if (targetBtn) {
                    ui.periodSelector.querySelectorAll('.period-btn').forEach((item) => item.classList.remove('active'));
                    targetBtn.classList.add('active');
                    ui.selectedMonths = pendingMonths;
                }
            }
            const currency = pending.currency || getAiCurrency();
            const current = Number(pending.current_month_amount) || ui.syncCurrentAndBalanceUi(ui.getAiPrice(plan), currency);
            ui.syncCurrentAndBalanceUi(ui.getAiPrice(plan), currency);
            if (ui.currentMonthInput) ui.currentMonthInput.value = current;
            if (ui.topupInput) {
                const topup = Number(pending.balance_topup) || 0;
                ui.topupInput.value = topup > 0 ? topup : '';
            }
            ui.applyAiItem({ refreshSummary: true });
        });
        cp._pendingAiItem = null;
        cp._pendingAiItems = null;

        cp.syncAiAgentAvailability();
    }

    function syncAiAgentAvailability(cp, options = {}) {
        attachAiCategoryHelpers(cp);
        const refreshSummary = options.refreshSummary === true;
        const section = document.getElementById('aiAgentSection');
        if (!section) {
            return;
        }

        const allowed = cp.isAiAgentAllowedForSelectedTariff();
        if (!allowed) {
            section.style.display = 'none';
            Object.values(cp._aiCategoryUi || {}).forEach((ui) => {
                if (ui.checkbox) ui.checkbox.checked = false;
                if (ui.body) ui.body.style.display = 'none';
                if (ui.currentMonthRow) ui.currentMonthRow.style.display = 'none';
                if (ui.topupRow) ui.topupRow.style.display = 'none';
            });
            const hadAi = cp.getSelectedAiItems().length > 0;
            cp.state.aiItems = {};
            cp.state.aiItem = null;
            cp._pendingAiItem = null;
            cp._pendingAiItems = null;
            if (hadAi && refreshSummary) {
                cp.updateSummary();
            }
            return;
        }

        if ((cp.aiTariffPlans || []).length > 0 || section.dataset.aiReady === '1') {
            section.style.display = '';
        }

        if (typeof cp._refreshAiGiftUi === 'function') {
            cp._refreshAiGiftUi({ refreshSummary });
        }
    }

    window.AiAgentBlock = {
        normalizeAiCategory,
        mapAiItemFromPayload,
        attach: attachAiCategoryHelpers,
        init: initAiAgentBlock,
        syncAvailability: syncAiAgentAvailability,
    };
})();
