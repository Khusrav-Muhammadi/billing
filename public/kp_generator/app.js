// ========================================
// CRM Commercial Proposal Generator
// ========================================

class CPGenerator {
    constructor() {
        this.config = null;
        this.clients = [];
        this.partners = [];
        this.clientPrices = {};
        this.combo = {
            client: { items: [], query: '', open: false, debounceTimer: null, controller: null },
            partner: { items: [], query: '', open: false, debounceTimer: null, controller: null }
        };
        this.state = {
            currency: 'USD',
            periodMonths: 6,
            selectedTariff: null,
            extraUsers: 0,
            selectedServices: {},
            implementationPrice: 0,
            onlineStorePrice: 0,
            customOneTimePayments: [],
            clientPhone: '',
            clientName: '',
            partnerName: '',
            managerId: '',
            managerName: '',
            token: '',
            leadId: '',
            dealStatusId: '',
            apiUrl: '',
            dealId: '',
            csrfToken: '',
            selectedClientId: null,
            selectedPartnerId: null,
            previousCurrency: 'USD',
            previousTariff: null,
            previousSuggestedPrice: 0,
            implementationPriceWasAutoSet: false
        };
        
        this.init();
    }
    
    async init() {
        await this.loadConfig();
        this.parseQueryParams();
        this.renderClientPartnerSelectors();
        this.renderAll();
        this.bindEvents();
    }
    
    async loadConfig() {
        try {
            const response = await fetch('/kp/config', {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!response.ok) throw new Error('Failed to load /kp/config');
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const body = await response.text();
                throw new Error(`Expected JSON from /kp/config, got "${contentType}": ${body.slice(0, 120)}`);
            }
            const payload = await response.json();

            this.config = payload.config || this.getDefaultConfig();
            this.clientPrices = payload.client_prices || {};
            this.clients = payload.clients || [];
            this.partners = payload.partners || [];
            this.combo.client.items = this.clients;
            this.combo.partner.items = this.partners;
        } catch (error) {
            console.error('Failed to load config from API, fallback to static file:', error);
            this.config = this.getDefaultConfig();
            try {
                const response = await fetch('data/config.json', {
                    headers: { 'Accept': 'application/json' }
                });
                this.config = await response.json();
            } catch (fileError) {
                console.error('Failed to load local config.json:', fileError);
            }
        }

        const tariffKeys = Object.keys(this.config.tariffs || {});
        if (!this.state.selectedTariff && tariffKeys.length) {
            this.state.selectedTariff = tariffKeys[0];
        }
    }
    
    getDefaultConfig() {
        return {
            currencies: {
                TJS: { symbol: "TJS", name: "Сомони" },
                UZS: { symbol: "UZS", name: "Сум" },
                USD: { symbol: "$", name: "Доллар" }
            },
            paymentPeriods: [
                { months: 6, discount: 0, label: "6 месяцев" },
                { months: 12, discount: 15, label: "12 месяцев (скидка 15%)" }
            ],
            tariffs: {},
            services: {},
            implementation: {},
            company: { name: "SHAMCRM", phone: "+998 78 555 7416", email: "info@shamcrm.com", website: "https://shamcrm.com" }
        };
    }

    setCurrencyFromClient(client) {
        if (!client) return;
        const currencies = this.config?.currencies || {};
        const byId = this.config?.currenciesById || {};

        const codeFromClient = client.currency && currencies[client.currency] ? client.currency : null;
        const codeFromId = client.currency_id && byId[String(client.currency_id)] ? byId[String(client.currency_id)] : null;
        const code = codeFromClient || codeFromId;

        if (code && currencies[code]) {
            this.state.currency = code;
        }
    }
    
    parseQueryParams() {
        const params = new URLSearchParams(window.location.search);
        
        this.state.clientPhone = params.get('phone') || this.state.clientPhone || '';
        const clientNameParam = params.get('client');
        if (clientNameParam) {
            this.state.clientName = clientNameParam;
        }
        this.state.csrfToken = params.get('csrf_token') || this.state.csrfToken || '';
        this.state.managerId = params.get('author_id') || '';
        this.state.managerName = params.get('author') || 'Менеджер';
        this.state.token = params.get('token') || '';
        this.state.leadId = params.get('lead_id') || '';
        this.state.dealStatusId = params.get('deal_status_id') || '';
        this.state.apiUrl = params.get('url') || '';
        this.state.dealId = params.get('deal_id') || '';
    }

    getApiHost() {
        if (!this.state.apiUrl) {
            return '';
        }

        const rawUrl = this.state.apiUrl.trim();
        if (!rawUrl) {
            return '';
        }

        try {
            const normalizedUrl = rawUrl.startsWith('http://') || rawUrl.startsWith('https://')
                ? rawUrl
                : `https://${rawUrl}`;
            return new URL(normalizedUrl).hostname.toLowerCase();
        } catch (error) {
            return rawUrl.split('/')[0].toLowerCase();
        }
    }

    getContactsForApiUrl() {
        const defaultContacts = {
            phone: this.config.company.phone,
            website: this.config.company.website || 'shamcrm.com',
            telegram: '@shamcrm_uz'
        };

        const apiHost = this.getApiHost();

        if (apiHost === 'tajikistan-back.shamcrm.com') {
            return {
                ...defaultContacts,
                phone: '+992488885050',
                telegram: '@Mrashuraliev'
            };
        }

        if (apiHost === 'fingroupcrm-back.shamcrm.com') {
            return {
                ...defaultContacts,
                phone: '+992488886363',
                telegram: '@FINGROUPCRM'
            };
        }

        return defaultContacts;
    }
    
    renderAll() {
        this.renderClientPartnerSelectors();
        this.renderTariffs();
        this.renderServices();
        this.updateExtraUsersSection();
        this.updateSummary();
    }

    getCurrentCurrency() {
        const currencies = this.config?.currencies || {};
        const currency = currencies?.[this.state.currency];
        if (currency && currency.symbol) return currency;
        // Fallback to first available currency from config
        const firstCode = Object.keys(currencies)[0];
        if (firstCode && currencies[firstCode]) {
            this.state.currency = firstCode;
            return currencies[firstCode];
        }
        // Last-resort fallback to avoid crashes
        return { symbol: this.state.currency || '', name: this.state.currency || '' };
    }

    getPriceByCurrency(prices) {
        if (!prices) return 0;
        const direct = prices?.[this.state.currency];
        if (typeof direct === 'number') return direct;
        const firstKey = Object.keys(prices)[0];
        const fallback = firstKey ? prices[firstKey] : 0;
        return typeof fallback === 'number' ? fallback : 0;
    }

    getTariffPricesMap(tariffKey) {
        const base = this.config?.tariffs?.[tariffKey]?.prices || {};
        const clientId = this.state.selectedClientId;
        const override = clientId ? (this.clientPrices?.[clientId]?.tariffs?.[tariffKey] || null) : null;
        return override ? { ...base, ...override } : base;
    }

    getServicePricesMap(serviceKey) {
        const base = this.config?.services?.[serviceKey]?.prices || {};
        const clientId = this.state.selectedClientId;
        const override = clientId ? (this.clientPrices?.[clientId]?.services?.[serviceKey] || null) : null;
        return override ? { ...base, ...override } : base;
    }

    getTariffMonthlyBase(tariffKey) {
        return this.getPriceByCurrency(this.getTariffPricesMap(tariffKey));
    }

    getTariffPriceByKey(tariffKey) {
        const base = this.getTariffMonthlyBase(tariffKey);
        return base * this.getPeriodDiscountMultiplier();
    }

    getOriginalTariffPriceByKey(tariffKey) {
        return this.getTariffMonthlyBase(tariffKey);
    }

    getExtraUserPricesMap(tariffKey) {
        const base = this.config?.tariffs?.[tariffKey]?.extraUserPrices || {};
        const clientId = this.state.selectedClientId;
        const override = clientId ? (this.clientPrices?.[clientId]?.extra_users?.[tariffKey] || null) : null;
        return override ? { ...base, ...override } : base;
    }

    getExtraUserMonthlyBase(tariffKey) {
        const map = this.getExtraUserPricesMap(tariffKey);
        const fromDb = this.getPriceByCurrency(map);
        if (fromDb > 0) return fromDb;
        // Fallback to old behavior if not configured in DB yet.
        return this.getTariffMonthlyBase(tariffKey) * 0.10;
    }

    getExtraUserPriceByKey(tariffKey) {
        return this.getExtraUserMonthlyBase(tariffKey) * this.getPeriodDiscountMultiplier();
    }

    getSelectedClient() {
        if (!this.state.selectedClientId) return null;
        return this.clients.find((c) => String(c.id) === String(this.state.selectedClientId)) || null;
    }

    getSelectedPartner() {
        if (!this.state.selectedPartnerId) return null;
        return this.partners.find((p) => String(p.id) === String(this.state.selectedPartnerId)) || null;
    }

    getPartnerDiscountPercent() {
        const partner = this.getSelectedPartner();
        const raw = partner?.procent_from_tariff ?? 0;
        const val = Number(raw) || 0;
        return Math.max(0, Math.min(100, val));
    }

    getPeriodDiscountPercent() {
        if (this.state.periodMonths === 12) return 15;
        if (this.state.periodMonths === 8) return 50;
        return 0;
    }

    getPeriodDiscountMultiplier() {
        return 1 - (this.getPeriodDiscountPercent() / 100);
    }

    applyPartnerDiscount(amount) {
        const a = Number(amount) || 0;
        const pct = this.getPartnerDiscountPercent();
        if (!pct) return a;
        return a * (1 - pct / 100);
    }

    getPaymentTypeForCard() {
        const code = String(this.state.currency || '').toLowerCase();
        if (code.includes('uz') || code === 'uzb' || code === 'uzs') return 'alif';
        if (code === 'tjs' || code === 'usd') return 'octo';
        // Default to visa-like flow (Octo)
        return 'octo';
    }

    getCardProviderLabel() {
        return this.getPaymentTypeForCard() === 'alif' ? 'Alif Pay' : 'Visa';
    }

    buildPaymentItems() {
        const items = [];
        if (!this.state.selectedTariff) return items;

        const tariffKey = this.state.selectedTariff;
        const tariff = this.config.tariffs[tariffKey];
        const periodMultiplier = this.getPeriodDiscountMultiplier();

        const tariffMonthlyBase = this.getTariffMonthlyBase(tariffKey);
        const tariffMonthly = tariffMonthlyBase * periodMultiplier;
        items.push({
            name: `Тариф "${tariff.name}" (${this.state.periodMonths} мес)`,
            price: this.applyPartnerDiscount(tariffMonthly * this.state.periodMonths)
        });

        if (this.state.extraUsers > 0) {
            const extraMonthly = this.getExtraUserMonthlyBase(tariffKey) * this.state.extraUsers * periodMultiplier;
            items.push({
                name: `Доп. пользователи (×${this.state.extraUsers})`,
                price: this.applyPartnerDiscount(extraMonthly * this.state.periodMonths)
            });
        }

        const selectedTariff = tariff;

        Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
            if (!serviceState.enabled) return;
            const service = this.config.services[key];
            if (!service || service.priceFromTariff) return;

            const isIncluded = selectedTariff?.includedServices?.includes(key);
            const basePrice = this.getPriceByCurrency(this.getServicePricesMap(key));
            const channels = service.hasChannels ? (serviceState.channels || 1) : 1;
            let monthlyPrice = 0;
            let displayChannels = channels;

            if (isIncluded && service.hasChannels) {
                const includedChannels = this.getIncludedChannels(tariffKey, key);
                const additionalChannels = Math.max(0, channels - includedChannels);
                if (additionalChannels <= 0) return;
                monthlyPrice = basePrice * additionalChannels;
                displayChannels = additionalChannels;
            } else if (isIncluded) {
                return;
            } else {
                monthlyPrice = basePrice * channels;
            }

            let name = service.name || key;
            if (displayChannels > 1) {
                name = `${name} (×${displayChannels})`;
            } else if (isIncluded && service.hasChannels) {
                name = `${name} (доп. ×${displayChannels})`;
            }

            items.push({
                name,
                price: this.applyPartnerDiscount(monthlyPrice * periodMultiplier * this.state.periodMonths)
            });
        });

        // Remove zero/negative lines
        return items.filter((i) => (Number(i.price) || 0) > 0);
    }

    submitPayment(paymentType) {
        if (!this.state.csrfToken) {
            alert('CSRF токен не найден. Обновите страницу.');
            return;
        }

        const client = this.getSelectedClient();
        if (!client) {
            alert('Выберите клиента.');
            return;
        }

        const items = this.buildPaymentItems();
        if (!items.length) {
            alert('Нет позиций для оплаты.');
            return;
        }

        const sum = items.reduce((s, i) => s + (Number(i.price) || 0), 0);

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/client-payment';
        form.target = '_top';

        const add = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = String(value ?? '');
            form.appendChild(input);
        };

        add('_token', this.state.csrfToken);
        add('name', client.name || this.state.clientName || 'Клиент');
        add('phone', client.phone || '');
        add('email', client.email || '');
        add('sum', sum);
        add('payment_type', paymentType);

        items.forEach((item, idx) => {
            add(`data[${idx}][name]`, item.name);
            add(`data[${idx}][price]`, item.price);
        });

        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    renderCurrencies() {
        const container = document.getElementById('currencySelector');
        if (!container || !this.config.currencies) return;
        container.innerHTML = '';

        Object.entries(this.config.currencies).forEach(([code]) => {
            const btn = document.createElement('button');
            btn.className = 'currency-btn' + (code === this.state.currency ? ' active' : '');
            btn.dataset.currency = code;
            btn.textContent = code;
            container.appendChild(btn);
        });
    }

    renderClientPartnerSelectors() {
        this.renderCombo('client');
        this.renderCombo('partner');
    }

    renderCombo(kind) {
        const input = document.getElementById(kind === 'client' ? 'clientSelect' : 'partnerSelect');
        const dropdown = document.getElementById(kind === 'client' ? 'clientDropdown' : 'partnerDropdown');
        if (!input || !dropdown) return;

        if (this.combo[kind].open) {
            input.value = this.combo[kind].query;
            dropdown.hidden = false;
            this.renderComboDropdown(kind);
        } else {
            dropdown.hidden = true;
            input.value = kind === 'client'
                ? (this.state.clientName || '')
                : (this.state.partnerName || '');
        }
    }

    renderComboDropdown(kind) {
        const dropdown = document.getElementById(kind === 'client' ? 'clientDropdown' : 'partnerDropdown');
        if (!dropdown) return;

        dropdown.innerHTML = '';
        const items = this.combo[kind].items || [];

        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'entity-combo-empty';
            empty.textContent = 'Ничего не найдено';
            dropdown.appendChild(empty);
            return;
        }

        items.forEach((item) => {
            const el = document.createElement('div');
            el.className = 'entity-combo-item';
            el.textContent = item.name || 'Без имени';
            el.setAttribute('role', 'option');
            const selectedId = kind === 'client' ? this.state.selectedClientId : this.state.selectedPartnerId;
            if (String(item.id) === String(selectedId)) {
                el.setAttribute('aria-selected', 'true');
            }
            // Use mousedown to avoid input blur closing before click.
            el.addEventListener('mousedown', (e) => e.preventDefault());
            el.addEventListener('click', () => this.selectComboItem(kind, item));
            dropdown.appendChild(el);
        });
    }

    openCombo(kind) {
        this.combo[kind].open = true;
        this.combo[kind].query = '';
        this.combo[kind].items = kind === 'client' ? this.clients : this.partners;
        this.renderCombo(kind);
    }

    closeCombo(kind) {
        this.combo[kind].open = false;
        this.combo[kind].query = '';
        this.renderCombo(kind);
    }

    scheduleComboSearch(kind, query) {
        this.combo[kind].query = query || '';

        if (this.combo[kind].debounceTimer) {
            clearTimeout(this.combo[kind].debounceTimer);
        }

        this.combo[kind].debounceTimer = setTimeout(async () => {
            try {
                await this.searchCombo(kind, this.combo[kind].query);
            } catch (e) {
                // Abort errors are expected when user keeps typing.
                if (e && e.name === 'AbortError') return;
                console.error(e);
            }
        }, 250);
    }

    async searchCombo(kind, query) {
        const term = (query || '').trim();
        if (term === '') {
            this.combo[kind].items = kind === 'client' ? this.clients : this.partners;
            this.renderCombo(kind);
            return;
        }

        const endpoint = kind === 'client' ? '/kp/clients' : '/kp/partners';
        if (this.combo[kind].controller) {
            this.combo[kind].controller.abort();
        }
        this.combo[kind].controller = new AbortController();

        const url = `${endpoint}?search=${encodeURIComponent(term)}`;
        const response = await fetch(url, {
            credentials: 'same-origin',
            signal: this.combo[kind].controller.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        if (!response.ok) {
            throw new Error(`Failed to load ${url}`);
        }
        const payload = await response.json();
        this.combo[kind].items = kind === 'client' ? (payload.clients || []) : (payload.partners || []);
        this.renderCombo(kind);
    }

    selectComboItem(kind, item) {
        if (kind === 'client') {
            this.state.selectedClientId = item.id;
            this.state.clientName = item.name || 'Клиент';
            this.setCurrencyFromClient(item);
            this.renderTariffs();
            this.renderServices();
            this.updateExtraUsersSection();
            this.updateSummary();
        } else {
            this.state.selectedPartnerId = item.id;
            this.state.partnerName = item.name || 'Партнер';
            // Partner percent affects all prices, so rerender totals/cards.
            this.renderTariffs();
            this.renderServices();
            this.updateExtraUsersSection();
            this.updateSummary();
        }

        this.closeCombo(kind);
    }

    updateHeaderInfo() {
        const clientNameEl = document.getElementById('clientName');
        const managerNameEl = document.getElementById('managerName');
        const partnerNameEl = document.getElementById('partnerName');

        if (clientNameEl) clientNameEl.textContent = this.state.clientName || 'Клиент';
        if (managerNameEl) managerNameEl.textContent = this.state.managerName || 'Менеджер';

        const partnerText = this.state.partnerName || 'Партнер не выбран';
        if (partnerNameEl) partnerNameEl.textContent = partnerText;
    }
    
    // ========================================
    // Render Tariffs
    // ========================================
    renderTariffs() {
        const grid = document.getElementById('tariffsGrid');
        grid.innerHTML = '';
        
        const tariffKeys = Object.keys(this.config.tariffs);
        const popularIndex = Math.floor(tariffKeys.length / 2);
        
        tariffKeys.forEach((key, index) => {
            const tariff = this.config.tariffs[key];
            const price = this.getTariffPriceByKey(key);
            const originalPrice = this.getOriginalTariffPriceByKey(key);
            const isPopular = index === popularIndex;
            const isSelected = this.state.selectedTariff === key;
            
            const card = document.createElement('div');
            card.className = `tariff-card${isPopular ? ' popular' : ''}${isSelected ? ' selected' : ''}`;
            card.dataset.tariff = key;
                
            const suggestedImplPrice = tariff.suggestedImplementationPrice 
                ? tariff.suggestedImplementationPrice[this.state.currency] 
                : 0;
            
            card.innerHTML = `
                <div class="tariff-select-indicator" style="margin-bottom: 250px !important;"></div>
              
                <div class="tariff-header">
                    <h3 class="tariff-name">${tariff.name}</h3>
                     </div>
                <div class="tariff-price">
                    <span class="price-value">${this.formatPrice(price)}</span>
                    <span class="price-period">/мес</span>
                    ${(this.state.periodMonths === 12 || this.state.periodMonths === 8) ? `<span class="original-price">${this.formatPrice(originalPrice)}</span>` : ''}
                </div>
            `;
            
            card.addEventListener('click', () => this.selectTariff(key));
            grid.appendChild(card);
        });
    }
    
    // Get included channels count for a service in a tariff
    getIncludedChannels(tariffKey, serviceKey) {
        if (!tariffKey || !serviceKey) return 0;
        
        const tariff = this.config.tariffs[tariffKey];
        const tf = tariff?.tariffFeatures || {};
        
        // Telegram bots: based on tariffFeatures
        if (serviceKey === 'telegram_b2c') {
            return tf.miniAppB2C?.channels || 0;
        }
        if (serviceKey === 'telegram_b2b') {
            return tf.miniAppB2B?.channels || 0;
        }
        
        // Extra funnels: based on tariffFeatures
        if (serviceKey === 'extra_funnel') {
            return tf.extraFunnels?.channels || 0;
        }
        
        return 0;
    }
    
    updateExtraUsersSection() {
        if (!this.state.selectedTariff) return;
        
        const tariff = this.config.tariffs[this.state.selectedTariff];
        const extraUsersSection = document.getElementById('extraUsersSection');
        if (extraUsersSection) {
            extraUsersSection.style.display = 'block';
            document.getElementById('includedUsers').textContent = tariff.users;
            document.getElementById('extraUserPrice').textContent = 
                this.formatPrice(this.getExtraUserPriceByKey(this.state.selectedTariff));
        }
    }
    
    selectTariff(tariffKey) {
        // Сохраняем предыдущий тариф перед сменой
        this.state.previousTariff = this.state.selectedTariff;
        
        this.state.selectedTariff = tariffKey;
        this.state.extraUsers = 0;
        
        const tariff = this.config.tariffs[tariffKey];
        const extraUsersSection = document.getElementById('extraUsersSection');
        extraUsersSection.style.display = 'block';
        
        document.getElementById('includedUsers').textContent = tariff.users;
        document.getElementById('extraUserPrice').textContent = 
            this.formatPrice(this.getExtraUserPriceByKey(tariffKey));
        document.getElementById('extraUsersInput').value = 0;
        
        // Clear all selected services first (reset to disabled and reset channels)
        Object.keys(this.state.selectedServices).forEach(serviceKey => {
            const service = this.config.services[serviceKey];
            if (service && service.hasChannels) {
                // Reset channels to 1 for services with channels
                this.state.selectedServices[serviceKey] = { enabled: false, channels: 1 };
            } else {
                // Just disable services without channels
                this.state.selectedServices[serviceKey].enabled = false;
            }
        });
        
        // Initialize included services with base channels
        if (tariff.includedServices) {
            tariff.includedServices.forEach(serviceKey => {
                const service = this.config.services[serviceKey];
                const includedChannels = this.getIncludedChannels(tariffKey, serviceKey);
                
                if (service && service.hasChannels) {
                    // Initialize with included channels
                    this.state.selectedServices[serviceKey] = { enabled: true, channels: includedChannels || 1 };
                } else {
                    // Service without channels - just enable it
                    this.state.selectedServices[serviceKey] = { enabled: true, channels: 1 };
                }
            });
        }
        
        // Enable SMS broadcast for premium and vip by default
        if (tariffKey === 'premium' || tariffKey === 'vip') {
            if (!this.state.selectedServices['sms_broadcast']) {
                this.state.selectedServices['sms_broadcast'] = { enabled: true, channels: 1 };
            } else {
                this.state.selectedServices['sms_broadcast'].enabled = true;
            }
        }
        
        this.renderServices();
        this.renderTariffs();
        this.updateSummary();
    }
    
    // tariff pricing is handled via *ByKey helpers (client override aware)
    
    // ========================================
    // Render Services
    // ========================================
    renderServices() {
        const grid = document.getElementById('servicesGrid');
        grid.innerHTML = '';
        
        const selectedTariff = this.state.selectedTariff 
            ? this.config.tariffs[this.state.selectedTariff] 
            : null;
        
        Object.entries(this.config.services).forEach(([key, service]) => {
            if (service.priceFromTariff) return;
            
            const isIncluded = selectedTariff?.includedServices?.includes(key);
            const isSelected = this.state.selectedServices[key]?.enabled;
            const channels = this.state.selectedServices[key]?.channels || (isIncluded ? 1 : 1);
            const unitPrice = this.getPriceByCurrency(this.getServicePricesMap(key));
            
            const card = document.createElement('div');
            card.className = `service-card${isSelected ? ' selected' : ''}${isIncluded ? ' included' : ''}`;
            card.dataset.service = key;

            let includedChannelsInfo = '';
            if (isIncluded && service.hasChannels) {
                const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                if (includedChannels > 0) {
                    includedChannelsInfo = `<p style="font-size: 0.75rem; color: #666; margin-top: 4px;">✓ ${includedChannels} ${includedChannels === 1 ? 'канал включен' : 'канала включено'} в тариф</p>`;
                }
            }
            
            card.innerHTML = `
                <div class="service-header">
                    <div class="service-info">
                        <h3>${service.name}${isIncluded ? ' <span style="font-size: 0.75rem; color: #666;">(включено)</span>' : ''}</h3>
                        <div class="service-price" style="margin-top: 6px; font-size: 0.9rem; color: #111;">
                            ${isIncluded && !service.hasChannels ? 'Включено' : `${this.formatPrice(unitPrice)}${service.hasChannels ? ' /канал/мес' : ' /мес'}`}
                        </div>
                        <p>${service.description}</p>
                        ${includedChannelsInfo}
                    </div>
                    <label class="service-toggle">
                        <input type="checkbox" 
                            ${isSelected ? 'checked' : ''} 
                            ${isIncluded ? 'disabled' : ''}
                            data-service="${key}">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                ${service.hasChannels ? `
                    <div class="service-channels">
                        <span class="channels-label">${isIncluded ? 'Доп. каналов:' : 'Каналов:'}</span>
                        <div class="channels-control">
                            ${(() => {
                                const includedChannels = isIncluded ? this.getIncludedChannels(this.state.selectedTariff, key) : 0;
                                const minChannels = isIncluded ? includedChannels : 1;
                                return `<button class="qty-btn minus" data-service="${key}" data-action="decrease" ${channels <= minChannels ? 'disabled' : ''}>−</button>
                            <input type="number" class="qty-input" value="${channels}" min="${minChannels}" data-service="${key}">
                            <button class="qty-btn plus" data-service="${key}" data-action="increase">+</button>`;
                            })()}
                        </div>
                        ${isIncluded ? (() => {
                            const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                            return `<span class="channels-note" style="font-size: 0.75rem; color: #666; margin-left: 8px;">(${includedChannels} ${includedChannels === 1 ? 'включен' : 'включено'}, доп. платно)</span>`;
                        })() : ''}
                    </div>
                ` : ''}
            `;
            
            grid.appendChild(card);
        });
        
        this.bindServiceEvents();
    }
    
    bindServiceEvents() {
        document.querySelectorAll('.service-toggle input').forEach(input => {
            input.addEventListener('change', (e) => {
                const serviceKey = e.target.dataset.service;
                if (!this.state.selectedServices[serviceKey]) {
                    this.state.selectedServices[serviceKey] = { enabled: false, channels: 1 };
                }
                this.state.selectedServices[serviceKey].enabled = e.target.checked;
                
                this.renderServices();
                this.updateSummary();
            });
        });
        
        document.querySelectorAll('.channels-control .qty-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const serviceKey = e.target.dataset.service;
                const action = e.target.dataset.action;
                
                if (!this.state.selectedServices[serviceKey]) {
                    this.state.selectedServices[serviceKey] = { enabled: false, channels: 1 };
                }
                
                const selectedTariff = this.state.selectedTariff 
                    ? this.config.tariffs[this.state.selectedTariff] 
                    : null;
                const isIncluded = selectedTariff?.includedServices?.includes(serviceKey);
                const minChannels = isIncluded ? this.getIncludedChannels(this.state.selectedTariff, serviceKey) : 1;
                
                if (action === 'increase') {
                    this.state.selectedServices[serviceKey].channels++;
                } else if (action === 'decrease' && this.state.selectedServices[serviceKey].channels > minChannels) {
                    this.state.selectedServices[serviceKey].channels--;
                }
                
                this.renderServices();
                this.updateSummary();
            });
        });
        
        document.querySelectorAll('.channels-control .qty-input').forEach(input => {
            input.addEventListener('change', (e) => {
                const serviceKey = e.target.dataset.service;
                const selectedTariff = this.state.selectedTariff 
                    ? this.config.tariffs[this.state.selectedTariff] 
                    : null;
                const isIncluded = selectedTariff?.includedServices?.includes(serviceKey);
                const minChannels = isIncluded ? this.getIncludedChannels(this.state.selectedTariff, serviceKey) : 1;
                const value = Math.max(minChannels, parseInt(e.target.value) || minChannels);
                
                if (!this.state.selectedServices[serviceKey]) {
                    this.state.selectedServices[serviceKey] = { enabled: false, channels: value };
                } else {
                    this.state.selectedServices[serviceKey].channels = value;
                }
                
                this.renderServices();
                this.updateSummary();
            });
        });
    }
    
    // ========================================
    // Update One-time Payments
    // ========================================
    getSuggestedImplementationPrice(currency) {
        let suggestedPrice = 0;
        if (this.state.selectedTariff) {
            const tariff = this.config.tariffs[this.state.selectedTariff];
            if (tariff && tariff.suggestedImplementationPrice) {
                suggestedPrice = tariff.suggestedImplementationPrice[currency || this.state.currency] || 0;
            }
        }
        if (!suggestedPrice && this.config.tariffs && this.config.tariffs.basic) {
            const basicTariff = this.config.tariffs.basic;
            if (basicTariff.suggestedImplementationPrice) {
                suggestedPrice = basicTariff.suggestedImplementationPrice[currency || this.state.currency] || 0;
            }
        }
        return suggestedPrice;
    }
    
    updateOneTimePayments() {
        const currency = this.getCurrentCurrency();
        
        // Update currency symbols
        const implCurrency = document.getElementById('implementationCurrency');
        const onlineStoreCurrency = document.getElementById('onlineStoreCurrency');
        if (implCurrency) implCurrency.textContent = currency.symbol;
        if (onlineStoreCurrency) onlineStoreCurrency.textContent = currency.symbol;
        
        // Get suggested implementation price
        const suggestedPrice = this.getSuggestedImplementationPrice();
        
        // Update implementation input and state
        const implInput = document.getElementById('implementationPrice');
        if (implInput) {
            // Проверяем, была ли смена валюты и нужно ли обновить значение
            if (this.state.previousCurrency && this.state.previousCurrency !== this.state.currency) {
                const oldSuggestedPrice = this.getSuggestedImplementationPrice(this.state.previousCurrency);
                // Если значение было установлено автоматически (равно suggestedPrice для старой валюты)
                if (Math.abs(this.state.implementationPrice - oldSuggestedPrice) < 0.01) {
                    // Обновляем на suggestedPrice для новой валюты
                    this.state.implementationPrice = suggestedPrice;
                    this.state.implementationPriceWasAutoSet = true;
                }
                // Сбрасываем флаг предыдущей валюты
                this.state.previousCurrency = null;
            }
            
            // Проверяем, была ли смена тарифа и нужно ли обновить значение
            if (this.state.previousTariff && this.state.previousTariff !== this.state.selectedTariff) {
                const oldTariff = this.config.tariffs[this.state.previousTariff];
                if (oldTariff && oldTariff.suggestedImplementationPrice) {
                    const oldSuggestedPrice = oldTariff.suggestedImplementationPrice[this.state.currency];
                    // Если значение было равно suggestedPrice для старого тарифа, обновляем на новый
                    if (Math.abs(this.state.implementationPrice - oldSuggestedPrice) < 0.01) {
                        this.state.implementationPrice = suggestedPrice;
                        this.state.implementationPriceWasAutoSet = true;
                    }
                }
                // Сбрасываем флаг предыдущего тарифа
                this.state.previousTariff = null;
            }
            
            // Если значение было установлено автоматически, обновляем его при изменении suggestedPrice
            if (this.state.implementationPriceWasAutoSet && suggestedPrice > 0) {
                // Обновляем значение на новый suggestedPrice
                this.state.implementationPrice = suggestedPrice;
                implInput.value = this.formatNumberInput(suggestedPrice);
            } else if (suggestedPrice > 0) {
                // Если текущее значение в state равно 0, устанавливаем suggestedPrice
                if (this.state.implementationPrice === 0 || !this.state.implementationPrice) {
                    this.state.implementationPrice = suggestedPrice;
                    this.state.implementationPriceWasAutoSet = true;
                    implInput.value = this.formatNumberInput(suggestedPrice);
                } else {
                    // Если пользователь уже ввел значение, используем его (с форматированием)
                    implInput.value = this.formatNumberInput(this.state.implementationPrice);
                }
            } else {
                // Если нет suggested price, используем текущее значение из state (с форматированием)
                implInput.value = this.formatNumberInput(this.state.implementationPrice || 0);
            }
        }
        
        // Update implementation hint - всегда показываем цену
        const implHint = document.getElementById('implementationHint');
        if (implHint) {
            if (suggestedPrice > 0) {
                implHint.textContent = `${this.formatPrice(suggestedPrice)} (скидка до 50 процентов)`;
            } else {
                implHint.textContent = '';
            }
        }
        
        // Update other input values
        const onlineStoreInput = document.getElementById('onlineStorePrice');
        if (onlineStoreInput) {
            onlineStoreInput.value = this.formatNumberInput(this.state.onlineStorePrice || 0);
        }
        
        // Check if Online Store is selected
        const onlineStoreSelected = this.state.selectedServices['online_store']?.enabled;
        const onlineStoreItem = document.getElementById('onlineStoreItem');
        if (onlineStoreItem) {
            onlineStoreItem.style.display = onlineStoreSelected ? 'block' : 'none';
            
            // Если услуга отключена, очищаем значение
            if (!onlineStoreSelected) {
                this.state.onlineStorePrice = 0;
                const onlineStoreInput = document.getElementById('onlineStorePrice');
                if (onlineStoreInput) {
                    onlineStoreInput.value = '0';
                }
            }
        }
        
        // Render custom one-time payments
        this.renderCustomOneTimePayments();
    }
    
    renderCustomOneTimePayments() {
        const container = document.getElementById('customOneTimePayments');
        if (!container) return;
        
        container.innerHTML = '';
        
        this.state.customOneTimePayments.forEach((payment, index) => {
            const item = document.createElement('div');
            item.className = 'one-time-item custom-one-time-item';
            item.innerHTML = `
                <div class="one-time-input-wrapper">
                    <label class="one-time-label">
                        <input type="text" 
                               class="custom-service-name-input" 
                               data-index="${index}"
                               placeholder="Название услуги"
                               value="${payment.name || ''}">
                    </label>
                    <div class="one-time-input-group">
                        <input type="number" 
                               class="one-time-input custom-service-price-input" 
                               data-index="${index}"
                               min="0" 
                               step="0.01" 
                               placeholder="0"
                               value="${payment.price || 0}">
                        <span class="one-time-currency">${this.getCurrentCurrency().symbol}</span>
                        <button class="remove-custom-service-btn" data-index="${index}" title="Удалить">×</button>
                    </div>
                </div>
            `;
            container.appendChild(item);
        });
        
        // Bind events for custom services
        this.bindCustomServiceEvents();
    }
    
    bindCustomServiceEvents() {
        // Name inputs
        document.querySelectorAll('.custom-service-name-input').forEach(input => {
            input.addEventListener('input', (e) => {
                const index = parseInt(e.target.dataset.index);
                if (this.state.customOneTimePayments[index]) {
                    this.state.customOneTimePayments[index].name = e.target.value;
                    this.updateSummary();
                }
            });
        });
        
        // Price inputs
        document.querySelectorAll('.custom-service-price-input').forEach(input => {
            input.addEventListener('input', (e) => {
                const index = parseInt(e.target.dataset.index);
                if (this.state.customOneTimePayments[index]) {
                    this.state.customOneTimePayments[index].price = parseFloat(e.target.value) || 0;
                    this.updateSummary();
                }
            });
        });
        
        // Remove buttons
        document.querySelectorAll('.remove-custom-service-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.target.dataset.index);
                this.removeCustomOneTimePayment(index);
            });
        });
    }
    
    addCustomOneTimePayment() {
        this.state.customOneTimePayments.push({
            name: '',
            price: 0
        });
        this.renderCustomOneTimePayments();
    }
    
    removeCustomOneTimePayment(index) {
        this.state.customOneTimePayments.splice(index, 1);
        this.renderCustomOneTimePayments();
        this.updateSummary();
    }
    
    // ========================================
    // Update Summary
    // ========================================
    updateSummary() {
        const summaryItems = document.getElementById('summaryItems');
        summaryItems.innerHTML = '';

        const rows = [];
        const months = this.state.periodMonths;
        const periodDiscountPercent = this.getPeriodDiscountPercent();
        const periodMultiplier = this.getPeriodDiscountMultiplier();
        const partnerPercent = this.getPartnerDiscountPercent();

        const selectedTariff = this.state.selectedTariff 
            ? this.config.tariffs[this.state.selectedTariff] 
            : null;

        if (this.state.selectedTariff && selectedTariff) {
            const baseTariffMonthly = this.getTariffMonthlyBase(this.state.selectedTariff);
            rows.push({
                name: `Тариф "${selectedTariff.name}"`,
                qty: 1,
                unitMonthly: baseTariffMonthly,
            });

            if (this.state.extraUsers > 0) {
                rows.push({
                    name: `Доп. пользователи`,
                    qty: this.state.extraUsers,
                    unitMonthly: this.getExtraUserMonthlyBase(this.state.selectedTariff),
                });
            }
        }
            
        Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
            if (!serviceState.enabled) return;
            
            const service = this.config.services[key];
            if (!service || service.priceFromTariff) return;
            
            const isIncluded = selectedTariff?.includedServices?.includes(key);
            
            const unitMonthly = this.getPriceByCurrency(this.getServicePricesMap(key));
            const channels = service.hasChannels ? serviceState.channels : 1;
            let displayChannels = channels;
            let qty = channels;
            
            // For included services with channels, only charge for additional channels
            if (isIncluded && service.hasChannels) {
                const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                const additionalChannels = channels - includedChannels;
                if (additionalChannels > 0) {
                    qty = additionalChannels;
                    displayChannels = additionalChannels;
                } else {
                    return; // No additional charges
                }
            } else if (isIncluded) {
                return; // Included service without channels - no charge
            }

            let displayText = service.name;
            if (isIncluded && service.hasChannels) {
                const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                displayText = `${service.name} (${includedChannels} ${includedChannels === 1 ? 'канал включен' : 'канала включено'}, +${displayChannels} доп.)`;
            } else if (channels > 1) {
                displayText = `${service.name} (×${channels})`;
            }

            rows.push({
                name: displayText,
                qty,
                unitMonthly,
            });
        });

        const computed = rows.map((r) => {
            const qty = Number(r.qty) || 0;
            const unitMonthly = Number(r.unitMonthly) || 0;
            const sum = unitMonthly * qty * months;
            const discountAmount = sum * (periodDiscountPercent / 100);
            const afterDiscount = sum - discountAmount;
            const partnerShare = afterDiscount * (partnerPercent / 100);
            return { ...r, qty, unitMonthly, months, sum, discountAmount, afterDiscount, partnerShare };
        });

        const totalSum = computed.reduce((s, r) => s + r.sum, 0);
        const totalDiscount = computed.reduce((s, r) => s + r.discountAmount, 0);
        const totalAfterDiscount = computed.reduce((s, r) => s + r.afterDiscount, 0);
        const totalPartnerShare = computed.reduce((s, r) => s + r.partnerShare, 0);
        const totalToPay = totalAfterDiscount - totalPartnerShare;

        if (computed.length > 0) {
            let tableHTML = '<div class="payments-table-wrap"><table class="payments-table payments-table-wide">';
            tableHTML += '<thead>';
            tableHTML += '<tr><th colspan="9" class="section-header">Платежи</th></tr>';
            tableHTML += '<tr>'
                + '<th>Название услуги</th>'
                + '<th>Кол-во</th>'
                + '<th>Цена</th>'
                + '<th>Месяц</th>'
                + '<th>Сумма</th>'
                + '<th>Скидка</th>'
                + '<th>Сумма со скидкой</th>'
                + '<th>% партнера</th>'
                + '<th>Доля партнера</th>'
                + '</tr>';
            tableHTML += '</thead><tbody>';

            computed.forEach((r) => {
                tableHTML += `
                    <tr>
                        <td>${r.name}</td>
                        <td>${r.qty}</td>
                        <td>${this.formatPrice(r.unitMonthly)}</td>
                        <td>${r.months}</td>
                        <td>${this.formatPrice(r.sum)}</td>
                        <td>${this.formatPrice(r.discountAmount)} (${periodDiscountPercent}%)</td>
                        <td>${this.formatPrice(r.afterDiscount)}</td>
                        <td>${partnerPercent}%</td>
                        <td>${this.formatPrice(r.partnerShare)}</td>
                    </tr>
                `;
            });

            tableHTML += `
                <tr class="payments-table-total">
                    <td style="font-weight:700; color:#111;">ИТОГО</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td style="font-weight:700; color:#111;">${this.formatPrice(totalSum)}</td>
                    <td style="font-weight:700; color:#111;">${this.formatPrice(totalDiscount)} (${periodDiscountPercent}%)</td>
                    <td style="font-weight:700; color:#111;">${this.formatPrice(totalAfterDiscount)}</td>
                    <td style="font-weight:700; color:#111;">${partnerPercent}%</td>
                    <td style="font-weight:700; color:#111;">${this.formatPrice(totalPartnerShare)}</td>
                </tr>
            `;

            tableHTML += '</tbody></table></div>';
            summaryItems.innerHTML = tableHTML;
        }

        const monthlyRaw = rows.reduce((s, r) => s + (Number(r.unitMonthly) || 0) * (Number(r.qty) || 0), 0);
        const monthlyAfterDiscount = monthlyRaw * periodMultiplier;
        const monthlyToPay = this.applyPartnerDiscount(monthlyAfterDiscount);
        
        // Build period details string
        const periodDetailsEl = document.getElementById('periodDetails');
        if (periodDetailsEl && this.state.selectedTariff) {
            const tariff = this.config.tariffs[this.state.selectedTariff];
            
            // Проверяем, есть ли дополнительные услуги или доп. пользователи
            let hasAdditionalServices = false;
            
            // Проверяем дополнительных пользователей
            if (this.state.extraUsers > 0) {
                hasAdditionalServices = true;
            }
            
            // Проверяем дополнительные услуги
            Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
                if (!serviceState.enabled) return;
                const service = this.config.services[key];
                if (!service || service.priceFromTariff) return;
                
                const isIncluded = selectedTariff?.includedServices?.includes(key);
                
                if (!isIncluded) {
                    // Услуга не входит в тариф
                    hasAdditionalServices = true;
                } else if (service.hasChannels) {
                    // Если услуга входит в тариф, но есть дополнительные каналы
                    const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                    if (serviceState.channels > includedChannels) {
                        hasAdditionalServices = true;
                    }
                }
            });
            
            // Формируем описание
            let detailsText = `Тариф "${tariff.name}"`;
            if (hasAdditionalServices) {
                detailsText += ` + Доп пакеты`;
            }
            
            if (this.state.periodMonths === 12) {
                detailsText += ` × ${this.state.periodMonths} мес (скидка 15%)`;
            } else if (this.state.periodMonths === 8) {
                detailsText += ` × ${this.state.periodMonths} мес (скидка 50%)`;
            } else {
                detailsText += ` × ${this.state.periodMonths} мес`;
            }

            if (partnerPercent > 0) {
                detailsText += `, партнер ${partnerPercent}%`;
            }
            
            periodDetailsEl.innerHTML = detailsText;
        } else if (periodDetailsEl) {
            periodDetailsEl.innerHTML = '';
        }
        
        const periodMonthlyTotalEl = document.getElementById('periodMonthlyTotal');
        if (periodMonthlyTotalEl) {
            if (monthlyRaw > 0) {
                periodMonthlyTotalEl.textContent = this.formatTotalPrice(monthlyToPay);
            } else {
                periodMonthlyTotalEl.textContent = '';
            }
        }
        
        const periodTotalEl = document.getElementById('periodTotal');
        if (periodTotalEl) periodTotalEl.textContent = this.formatTotalPrice(totalToPay);

        const grandTotalEl = document.getElementById('grandTotal');
        if (grandTotalEl) grandTotalEl.textContent = this.formatTotalPrice(totalToPay);
        
        if (summaryItems.innerHTML === '') {
            summaryItems.innerHTML = `
                <div class="summary-item" style="justify-content: center; color: var(--text-muted);">
                    Выберите тариф для расчёта
                </div>
            `;
        }
    }

    formatPrice(amount) {
        const currency = this.getCurrentCurrency();
        
        if (this.state.currency === 'USD') {
            // Для USD показываем 2 знака после запятой без округления
            return `$${amount.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }
        
        // Для других валют показываем без округления (с точностью до 2 знаков если есть дробная часть)
        const formatted = amount.toLocaleString('ru-RU', { 
            minimumFractionDigits: amount % 1 === 0 ? 0 : 2, 
            maximumFractionDigits: 2 
        });
        return `${formatted} ${currency.symbol}`;
    }
    
    formatTotalPrice(amount) {
        // Округляем только итоговую сумму
        const currency = this.getCurrentCurrency();
        const rounded = Math.round(amount);
        
        if (this.state.currency === 'USD') {
            return `$${rounded.toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
        }
        
        return `${rounded.toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} ${currency.symbol}`;
    }
    
    getCurrencyDisplayName() {
        // Возвращает валюту для отображения в PDF
        const currencyMap = {
            'TJS': 'сомони',
            'UZS': 'сум',
            'USD': '$',
            'EUR': '€',
            'RUB': 'руб.'
        };
        return currencyMap[this.state.currency] || this.state.currency;
    }
    
    formatNumberInput(value) {
        // Форматирует число для отображения в input с разделителями тысяч
        if (!value && value !== 0) return '';
        const num = parseFloat(value.toString().replace(/\s/g, '').replace(/,/g, '.')) || 0;
        // Используем пробел как разделитель тысяч (как в ru-RU)
        return num.toLocaleString('ru-RU', { 
            minimumFractionDigits: 0, 
            maximumFractionDigits: 2,
            useGrouping: true
        });
    }
    
    parseNumberInput(value) {
        // Парсит отформатированное число из input (убирает разделители)
        if (!value) return 0;
        // Убираем все пробелы (обычные и неразрывные - разделители тысяч) и заменяем запятую на точку
        const cleaned = value.toString()
            .replace(/\s/g, '')  // обычные пробелы
            .replace(/\u00A0/g, '')  // неразрывные пробелы
            .replace(/,/g, '.');  // запятая на точку для десятичных
        return parseFloat(cleaned) || 0;
    }

    bindEvents() {
        const clientInput = document.getElementById('clientSelect');
        if (clientInput) {
            clientInput.addEventListener('focus', () => {
                // Keep dropdown closed on click/focus; open only on typing or ArrowDown.
                this.closeCombo('client');
            });
            clientInput.addEventListener('click', () => {
                // Prevent "immediate dropdown" UX on click.
                this.closeCombo('client');
            });
            clientInput.addEventListener('input', (e) => {
                this.combo.client.open = true;
                this.scheduleComboSearch('client', e.target.value);
                this.renderCombo('client');
            });
            clientInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.closeCombo('client');
                if (e.key === 'ArrowDown') {
                    if (!this.combo.client.open) this.openCombo('client');
                }
                if (e.key === 'Enter') {
                    const first = (this.combo.client.items || [])[0];
                    if (first) this.selectComboItem('client', first);
                }
            });
        }

        const partnerInput = document.getElementById('partnerSelect');
        if (partnerInput) {
            partnerInput.addEventListener('focus', () => {
                this.closeCombo('partner');
            });
            partnerInput.addEventListener('click', () => {
                this.closeCombo('partner');
            });
            partnerInput.addEventListener('input', (e) => {
                this.combo.partner.open = true;
                this.scheduleComboSearch('partner', e.target.value);
                this.renderCombo('partner');
            });
            partnerInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.closeCombo('partner');
                if (e.key === 'ArrowDown') {
                    if (!this.combo.partner.open) this.openCombo('partner');
                }
                if (e.key === 'Enter') {
                    const first = (this.combo.partner.items || [])[0];
                    if (first) this.selectComboItem('partner', first);
                }
            });
        }

        document.addEventListener('mousedown', (e) => {
            const clientCombo = document.getElementById('clientCombo');
            const partnerCombo = document.getElementById('partnerCombo');

            if (clientCombo && !clientCombo.contains(e.target)) {
                this.closeCombo('client');
            }
            if (partnerCombo && !partnerCombo.contains(e.target)) {
                this.closeCombo('partner');
            }
        });
        
        document.querySelectorAll('.period-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const button = e.target.closest('.period-btn');
                document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
                button.classList.add('active');
                this.state.periodMonths = parseInt(button.dataset.months);
                this.renderAll();
            });
        });
        
        document.getElementById('usersMinusBtn').addEventListener('click', () => {
            if (this.state.extraUsers > 0) {
                this.state.extraUsers--;
                document.getElementById('extraUsersInput').value = this.state.extraUsers;
                this.updateSummary();
            }
        });
        
        document.getElementById('usersPlusBtn').addEventListener('click', () => {
            this.state.extraUsers++;
            document.getElementById('extraUsersInput').value = this.state.extraUsers;
            this.updateSummary();
        });
        
        document.getElementById('extraUsersInput').addEventListener('change', (e) => {
            this.state.extraUsers = Math.max(0, parseInt(e.target.value) || 0);
            e.target.value = this.state.extraUsers;
            this.updateSummary();
        });
        
        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveAndDownloadPDF());
        }
        document.getElementById('closeSuccessBtn').addEventListener('click', () => this.closeModal('successModal'));

        const payBtn = document.getElementById('payBtn');
        if (payBtn) {
            payBtn.addEventListener('click', () => {
                const client = this.getSelectedClient();
                const totals = this.calculateTotals();
                const payModal = document.getElementById('payModal');
                const payClientName = document.getElementById('payClientName');
                const payAmount = document.getElementById('payAmount');
                const payCardProvider = document.getElementById('payCardProvider');

                if (payClientName) payClientName.textContent = client?.name || this.state.clientName || '—';
                if (payAmount) payAmount.textContent = this.formatTotalPrice(totals.grand || 0);
                if (payCardProvider) payCardProvider.textContent = this.getCardProviderLabel();

                if (payModal) payModal.classList.add('active');
            });
        }

        const payCloseBtn = document.getElementById('payCloseBtn');
        if (payCloseBtn) {
            payCloseBtn.addEventListener('click', () => this.closeModal('payModal'));
        }

        const payByCardBtn = document.getElementById('payByCardBtn');
        if (payByCardBtn) {
            payByCardBtn.addEventListener('click', () => {
                const paymentType = this.getPaymentTypeForCard(); // alif or octo
                this.submitPayment(paymentType);
            });
        }

        const payByInvoiceBtn = document.getElementById('payByInvoiceBtn');
        if (payByInvoiceBtn) {
            payByInvoiceBtn.addEventListener('click', () => {
                this.submitPayment('invoice');
            });
        }
        
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });
    }
    
    // ========================================
    // PDF Generation
    // ========================================
    generatePDFContent() {
        const date = new Date().toLocaleDateString('ru-RU');
        const currency = this.getCurrentCurrency();
        let periodText = '6 месяцев';
        if (this.state.periodMonths === 12) {
            periodText = '12 месяцев (скидка 15%)';
        } else if (this.state.periodMonths === 8) {
            periodText = '8 месяцев (скидка 50%)';
        }
        
        if (!this.state.selectedTariff) {
            return '<div>Выберите тариф для генерации КП</div>';
        }
        
        const tariff = this.config.tariffs[this.state.selectedTariff];
        const tariffPrice = this.getTariffPriceByKey(this.state.selectedTariff);
        const selectedTariff = this.state.selectedTariff 
            ? this.config.tariffs[this.state.selectedTariff] 
            : null;
        
        // Получаем дополнительные пакеты (ежемесячные услуги)
        const additionalPackages = this.getAdditionalPackages();
        
        // Получаем единоразовые оплаты
        const oneTimePayments = [];
        
        // Получаем итоговые суммы
        const totals = this.calculateTotals();
        const contacts = this.getContactsForApiUrl();
        
        let html = `
            <style>
                ${this.getProposalPDFStyles()}
            </style>
            <!-- PAGE 1 - COVER -->
                <div class="page cover-page">
                    <div class="logo-container">
                        <div class="logo-icon">
                            <svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect width="160" height="160" rx="20" fill="#2B4BFF"/>
                                <text x="80" y="100" font-family="Arial, sans-serif" font-size="60" font-weight="bold" fill="white" text-anchor="middle">CRM</text>
                            </svg>
                        </div>
                        <div class="logo-text">
                            <span>SHAM</span>
                            <span class="crm-badge">CRM</span>
                        </div>
                    </div>

                    <h1 class="main-title">КОММЕРЧЕСКОЕ<br>ПРЕДЛОЖЕНИЕ</h1>

                    <div class="info-section">
                        <div class="info-row">
                            <div class="info-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                            <span class="info-label">Клиент: ${this.state.clientName}</span>
                        </div>

                        <div class="info-row">
                            <div class="info-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                            <span class="info-label">Менеджер: ${this.state.managerName}</span>
                        </div>

                        <div class="info-row">
                            <div class="info-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <span class="info-label">Дата: ${date}</span>
                        </div>
                    </div>

                    <div class="cover-decoration">
                        <svg class="wave-shape" viewBox="0 0 350 150" preserveAspectRatio="none">
                            <path class="wave-dark" d="M350 150 L350 0 C300 20 250 60 180 80 C110 100 50 90 0 150 Z"/>
                            <path class="wave-light" d="M350 150 L350 30 C310 50 270 80 200 95 C130 110 70 100 0 150 Z"/>
                        </svg>
                    </div>
                </div>

                <!-- PAGE 2 - TARIFF -->
                <div class="page tariff-page">
                    <div class="header-decoration"></div>
                    
                    <div class="page-header">
                        <div class="logo-text">
                            <span>SHAM</span>
                            <span class="crm-badge">CRM</span>
                        </div>
                    </div>

                    <div class="tariff-header">
                        <span class="tariff-title">Тариф shamCRM: <strong>${tariff.name}</strong></span>
                        <span class="tariff-title">Срок: <strong>${periodText}</strong></span>
                    </div>

                    <!-- Таблица ключевых фичей тарифа -->
                    <div class="tariff-table">
                        <div class="table-header">
                            <div class="table-header-cell">Входит в тариф</div>
                            <div class="table-header-cell">
                                <svg class="checkmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </div>
                        </div>
                        ${(tariff.features || []).map(feature => `
                            <div class="table-row">
                                <div class="table-cell">
                                    <div class="feature-title">${feature}</div>
                                </div>
                                <div class="table-cell">
                                    <svg class="checkmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </div>
                            </div>
                        `).join('')}
                    </div>

                    <div class="summary-section">
                        <div class="summary-row">Пользователей в тарифе: <strong>${tariff.users}</strong></div>
                        <div class="summary-row">Стоимость тарифа: <strong>${this.formatPrice(tariffPrice)}/мес</strong></div>
                        ${this.state.extraUsers > 0 ? `
                            <div class="summary-row">Доп. пользователи: <strong>${this.state.extraUsers} × ${this.formatPrice(this.getExtraUserPriceByKey(this.state.selectedTariff))} = ${this.formatPrice(this.getExtraUserPriceByKey(this.state.selectedTariff) * this.state.extraUsers)}/мес</strong></div>
                        ` : ''}
                    </div>

                    ${additionalPackages.length > 0 ? `
                        <!-- Таблица дополнительных пакетов -->
                        <h3 class="section-subtitle" style="margin-top: 40px; margin-bottom: 20px;">Дополнительные пакеты</h3>
                        <div class="tariff-table">
                            <div class="table-header">
                                <div class="table-header-cell">Название пакета</div>
                                <div class="table-header-cell" style="width: 150px; text-align: right;">Стоимость/мес</div>
                            </div>
                            ${additionalPackages.map(pkg => `
                                <div class="table-row">
                                    <div class="table-cell">
                                        <div class="feature-title">${pkg.name}</div>
                                        ${pkg.description ? `<div class="feature-subtitle">${pkg.description}</div>` : ''}
                                    </div>
                                    <div class="table-cell" style="text-align: right; font-weight: 600;">
                                        ${this.formatPrice(pkg.price)}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}

                    ${oneTimePayments.length > 0 ? `
                        <!-- Таблица единоразовых оплат -->
                        <h3 class="section-subtitle" style="margin-top: 40px; margin-bottom: 20px;">Единоразовые оплаты</h3>
                        <div class="tariff-table">
                            <div class="table-header">
                                <div class="table-header-cell">Название услуги</div>
                                <div class="table-header-cell" style="width: 150px; text-align: right;">Стоимость</div>
                            </div>
                            ${oneTimePayments.map(payment => `
                                <div class="table-row">
                                    <div class="table-cell">
                                        <div class="feature-title">${payment.name}</div>
                                        ${payment.description ? `<div class="feature-subtitle">${payment.description}</div>` : ''}
                                    </div>
                                    <div class="table-cell" style="text-align: right; font-weight: 600;">
                                        ${this.formatPrice(payment.price)}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}

                    <div class="bottom-decoration">
                        <svg class="bottom-wave" viewBox="0 0 250 100" preserveAspectRatio="none">
                            <path fill="#1a237e" d="M0 100 L0 0 C30 10 80 40 130 50 C180 60 220 50 250 100 Z"/>
                            <path fill="#2B4BFF" d="M0 100 L0 20 C40 30 90 55 140 60 C190 65 230 55 250 100 Z"/>
                        </svg>
                    </div>
                </div>

                <!-- PAGE 3 - TOTAL COST -->
                <div class="page cost-page">
                    <div class="header-decoration"></div>
                    
                    <div class="page-header">
                        <div class="logo-text">
                            <span>SHAM</span>
                            <span class="crm-badge">CRM</span>
                        </div>
                    </div>

                    <h2 class="section-title">Итоговая стоимость</h2>

                    <div class="cost-table">
                        <div class="table-header">
                            <div class="table-header-cell">Статья</div>
                            <div class="table-header-cell">Сумма</div>
                        </div>
                        
                        <div class="table-row">
                            <div class="table-cell">Тариф "${tariff.name}" (${periodText})</div>
                            <div class="table-cell">${this.formatTotalPrice(tariffPrice * this.state.periodMonths)}</div>
                        </div>
                        
                        ${this.state.extraUsers > 0 ? `
                            <div class="table-row">
                                <div class="table-cell">Доп. пользователи (×${this.state.extraUsers})</div>
                                <div class="table-cell">${this.formatTotalPrice(this.getExtraUserPriceByKey(this.state.selectedTariff) * this.state.extraUsers * this.state.periodMonths)}</div>
                            </div>
                        ` : ''}
                        
                        ${additionalPackages.length > 0 ? additionalPackages.map(pkg => `
                            <div class="table-row">
                                <div class="table-cell">${pkg.name} (${this.state.periodMonths} мес.)</div>
                                <div class="table-cell">${this.formatTotalPrice(pkg.price * this.state.periodMonths)}</div>
                            </div>
                        `).join('') : ''}
                        
                        ${oneTimePayments.length > 0 ? oneTimePayments.map(payment => `
                            <div class="table-row">
                                <div class="table-cell">${payment.name}</div>
                                <div class="table-cell">${this.formatPrice(payment.price)}</div>
                            </div>
                        `).join('') : ''}
                        
                        <div class="table-row total-row">
                            <div class="table-cell">ИТОГО</div>
                            <div class="table-cell">${this.formatTotalPrice(totals.grand)}</div>
                        </div>
                    </div>

                    <div class="tagline">
                        <p class="tagline-text"><span>shamCRM</span> — CRM, которая<br>реально внедряется и работает</p>
                    </div>

                    <div class="contacts-section">
                        <div class="contact-item">
                            <div class="contact-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                            </div>
                            <span class="contact-value">${contacts.phone}</span>
                        </div>

                        <div class="contact-item">
                            <div class="contact-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="2" y1="12" x2="22" y2="12"/>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                </svg>
                            </div>
                            <span class="contact-value">${contacts.website}</span>
                        </div>
                    </div>

                    <p class="validity-notice">Предложение действительно 30 дней</p>

                    <div class="bottom-decoration-left">
                        <svg viewBox="0 0 250 120" preserveAspectRatio="none" style="width: 100%; height: 100%;">
                            <path fill="#1a237e" d="M0 120 L0 0 C40 20 100 60 160 70 C200 78 240 70 250 120 Z"/>
                            <path fill="#2B4BFF" d="M0 120 L0 30 C50 45 110 75 170 82 C210 87 245 80 250 120 Z"/>
                        </svg>
                    </div>

                    <div class="bottom-decoration-right">
                        <svg viewBox="0 0 250 120" preserveAspectRatio="none" style="width: 100%; height: 100%;">
                            <path fill="#1a237e" d="M250 120 L250 0 C210 20 150 60 90 70 C50 78 10 70 0 120 Z"/>
                            <path fill="#2B4BFF" d="M250 120 L250 30 C200 45 140 75 80 82 C40 87 5 80 0 120 Z"/>
                        </svg>
                    </div>
                </div>
        `;
        
        return html;
    }
    
    getAdditionalPackages() {
        const packages = [];
        const selectedTariff = this.state.selectedTariff 
            ? this.config.tariffs[this.state.selectedTariff] 
            : null;
        
        Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
            if (!serviceState.enabled) return;
            
            const service = this.config.services[key];
            if (!service || service.priceFromTariff) return;
            
            const isIncluded = selectedTariff?.includedServices?.includes(key);
            
            // Пропускаем включенные услуги без доп. каналов
            if (isIncluded) {
                if (service.hasChannels) {
                    const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                    const additionalChannels = serviceState.channels - includedChannels;
                    if (additionalChannels > 0) {
                        const price = this.getPriceByCurrency(this.getServicePricesMap(key)) * additionalChannels;
                        packages.push({
                            name: `${service.name} (доп. ×${additionalChannels})`,
                            description: service.description,
                            price: price
                        });
                    }
                }
                return;
            }
            
            // Обычные услуги
            if (service.type !== 'one_time') {
                const channels = service.hasChannels ? serviceState.channels : 1;
                const price = this.getPriceByCurrency(this.getServicePricesMap(key)) * channels;
                packages.push({
                    name: service.name + (channels > 1 ? ` (×${channels})` : ''),
                    description: service.description,
                    price: price
                });
            }
        });
        
        return packages;
    }
    
    getOneTimePayments() {
        const payments = [];
        // Внедрение
        if (this.state.implementationPrice > 0) {
            payments.push({
                name: 'Внедрение и обучение',
                description: '',
                price: this.state.implementationPrice
            });
        }
        
        // Запуск интернет магазина
        if (this.state.selectedServices['online_store']?.enabled && this.state.onlineStorePrice > 0) {
            payments.push({
                name: 'Запуск интернет магазина',
                description: '',
                price: this.state.onlineStorePrice
            });
        }
        
        // Пользовательские разовые платежи
        this.state.customOneTimePayments.forEach(payment => {
            if (payment.name && payment.price > 0) {
                payments.push({
                    name: payment.name,
                    description: '',
                    price: payment.price
                });
            }
        });
        
        return payments;
    }
    
    getSelectedServicesForPDF() {
        const services = [];
        const selectedTariff = this.state.selectedTariff 
            ? this.config.tariffs[this.state.selectedTariff] 
            : null;
        
        if (this.state.selectedTariff && this.state.extraUsers > 0) {
            const tariff = this.config.tariffs[this.state.selectedTariff];
            services.push({
                name: `Дополнительные пользователи (×${this.state.extraUsers})`,
                type: 'monthly',
                price: this.getExtraUserPriceByKey(this.state.selectedTariff) * this.state.extraUsers
            });
        }
        
        Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
            if (!serviceState.enabled) return;
            
            const service = this.config.services[key];
            if (!service || service.priceFromTariff) return;
            
            const isIncluded = selectedTariff?.includedServices?.includes(key);
            
            // Other services
            if (isIncluded) {
                // For included services with channels, only charge for additional
                if (service.hasChannels) {
                    const channels = serviceState.channels || 1;
                    const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                    const additionalChannels = Math.max(0, channels - includedChannels);
                    if (additionalChannels > 0) {
                        const totalPrice = this.getPriceByCurrency(this.getServicePricesMap(key)) * additionalChannels;
                        services.push({
                            name: `${service.name} (доп. ×${additionalChannels})`,
                            type: service.type === 'one_time' ? 'one_time' : 'monthly',
                            price: totalPrice
                        });
                    }
                }
                return;
            }
            
            const basePrice = this.getPriceByCurrency(this.getServicePricesMap(key));
            const channels = service.hasChannels ? serviceState.channels : 1;
            let totalPrice = basePrice * channels;
            let name = service.name;
            if (channels > 1) name = `${service.name} (×${channels})`;
            
            services.push({
                name,
                type: service.type === 'one_time' ? 'one_time' : 'monthly',
                price: totalPrice
            });
        });
        
        return services;
    }
    
    calculateTotals() {
        let monthlyRaw = 0;
        const months = this.state.periodMonths;
        const periodMultiplier = this.getPeriodDiscountMultiplier();
        
        if (this.state.selectedTariff) {
            monthlyRaw += this.getTariffMonthlyBase(this.state.selectedTariff);
            
            if (this.state.extraUsers > 0) {
                monthlyRaw += this.getExtraUserMonthlyBase(this.state.selectedTariff) * this.state.extraUsers;
            }
        }
        
        const selectedTariff = this.state.selectedTariff 
            ? this.config.tariffs[this.state.selectedTariff] 
            : null;
            
        Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
            if (!serviceState.enabled) return;
            
            const service = this.config.services[key];
            if (!service || service.priceFromTariff) return;
            
            const isIncluded = selectedTariff?.includedServices?.includes(key);
            
            const basePrice = this.getPriceByCurrency(this.getServicePricesMap(key));
            const channels = service.hasChannels ? serviceState.channels : 1;
            let totalPrice = 0;
            
            // For included services with channels, only charge for additional channels
            if (isIncluded && service.hasChannels) {
                const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                const additionalChannels = Math.max(0, channels - includedChannels); // Channels beyond included are paid
                if (additionalChannels > 0) {
                    totalPrice = basePrice * additionalChannels;
                } else {
                    return; // No additional charges
                }
            } else if (isIncluded) {
                return; // Included service without channels - no charge
            } else {
                totalPrice = basePrice * channels;
            }
            
            monthlyRaw += totalPrice;
        });
        
        const monthlyAfterDiscount = monthlyRaw * periodMultiplier;
        const monthlyToPay = this.applyPartnerDiscount(monthlyAfterDiscount);
        const periodToPay = monthlyToPay * months;
        
        return {
            monthly: monthlyToPay,
            period: periodToPay,
            grand: periodToPay
        };
    }
    
    // ========================================
    // Save & Download PDF
    // ========================================
    // Формирует данные для отправки в API генерации PDF
    preparePDFData() {
        if (!this.state.selectedTariff) {
            return null;
        }
        
        const tariff = this.config.tariffs[this.state.selectedTariff];
        const tariffPrice = this.getTariffPriceByKey(this.state.selectedTariff);
        const totals = this.calculateTotals();
        
        // Формат даты d.m.Y
        const now = new Date();
        const date = `${String(now.getDate()).padStart(2, '0')}.${String(now.getMonth() + 1).padStart(2, '0')}.${now.getFullYear()}`;
        
        const tf = tariff.tariffFeatures || {};
        const selectedTariff = this.config.tariffs[this.state.selectedTariff];
        
        // Формируем tariff_features на основе данных тарифа
        const tariffFeatures = [];
        
        // Количество пользователей
        tariffFeatures.push({
            name: "Количество пользователей",
            value: `${tf.users || tariff.users} шт.`
        });
        
        // Дашборд
        if (tf.dashboard) {
            tariffFeatures.push({ name: "Дашборд" });
        }
        
        // Интеграции с соц.сетями
        if (tf.socialIntegrations) {
            const social = tf.socialIntegrations;
            if (social.telegram && social.telegram.channels > 0) {
                tariffFeatures.push({
                    name: "Интеграция Telegram",
                    value: `${social.telegram.channels} канал${social.telegram.channels > 1 ? 'а' : ''}`
                });
            }
            if (social.whatsapp && social.whatsapp.channels > 0) {
                tariffFeatures.push({
                    name: "Интеграция WhatsApp",
                    value: `${social.whatsapp.channels} канал${social.whatsapp.channels > 1 ? 'а' : ''}`
                });
            }
            if (social.instagram && social.instagram.channels > 0) {
                tariffFeatures.push({
                    name: "Интеграция Instagram",
                    value: `${social.instagram.channels} канал${social.instagram.channels > 1 ? 'а' : ''}`
                });
            }
            if (social.messenger && social.messenger.channels > 0) {
                tariffFeatures.push({
                    name: "Интеграция Messenger",
                    value: `${social.messenger.channels} канал${social.messenger.channels > 1 ? 'а' : ''}`
                });
            }
            if (social.email && social.email.included) {
                tariffFeatures.push({ name: "Интеграция Почта" });
            }
        }
        
        // Мобильное приложение
        if (tf.mobileApp) {
            tariffFeatures.push({ name: "Мобильное приложение" });
        }
        
        // Контроль доступа
        if (tf.accessControl) {
            tariffFeatures.push({ name: "Контроль доступ" });
        }
        
        // Воронка продаж
        if (tf.salesFunnels) {
            tariffFeatures.push({
                name: "Воронка продаж",
                value: `${tf.salesFunnels} воронк${tf.salesFunnels > 1 ? 'и' : 'а'}`
            });
        }
        
        // Управление задачами
        if (tf.taskManagement) {
            tariffFeatures.push({ name: "Управление задачами" });
        }
        
        // Календарь
        if (tf.calendar) {
            tariffFeatures.push({ name: "Календарь" });
        }
        
        // СМС рассылка
        if (tf.smsBroadcast) {
            tariffFeatures.push({ name: "Смс рассылка" });
        }
        
        // Формируем modules (все доступные модули/услуги)
        const modules = [];
        
        // Mini-app B2B
        const miniAppB2B = tf.miniAppB2B || {};
        const b2bIncluded = miniAppB2B.channels > 0 && miniAppB2B.discount === 100;
        const b2bSelected = this.state.selectedServices['telegram_b2b']?.enabled && !b2bIncluded;
        modules.push({
            name: "Mini-app B2B",
            status: b2bIncluded ? "included" : (b2bSelected ? "selected" : "not_available"),
            price: b2bSelected ? this.getPriceByCurrency(this.config.services['telegram_b2b']?.prices) : 0
        });
        
        // Mini-app B2C
        const miniAppB2C = tf.miniAppB2C || {};
        const b2cIncluded = miniAppB2C.channels > 0 && miniAppB2C.discount === 100;
        const b2cSelected = this.state.selectedServices['telegram_b2c']?.enabled && !b2cIncluded;
        modules.push({
            name: "Mini-app B2C",
            status: b2cIncluded ? "included" : (b2cSelected ? "selected" : "not_available"),
            price: b2cSelected ? this.getPriceByCurrency(this.config.services['telegram_b2c']?.prices) : 0
        });
        
        // Интернет-магазин
        const onlineStoreSelected = this.state.selectedServices['online_store']?.enabled;
        modules.push({
            name: "Интернет-магазин",
            status: onlineStoreSelected ? "selected" : "not_available",
            price: onlineStoreSelected ? this.getPriceByCurrency(this.config.services['online_store']?.prices) : 0
        });
        
        // Складской учёт и касса
        const warehouseSelected = this.state.selectedServices['warehouse']?.enabled;
        modules.push({
            name: "Складской учёт и касса",
            status: warehouseSelected ? "selected" : "not_available",
            price: warehouseSelected ? this.getPriceByCurrency(this.config.services['warehouse']?.prices) : 0
        });
        
        // SMS Рассылка
        const smsIncluded = selectedTariff?.includedServices?.includes('sms_broadcast');
        const smsSelected = this.state.selectedServices['sms_broadcast']?.enabled && !smsIncluded;
        modules.push({
            name: "SMS Рассылка",
            status: smsIncluded ? "included" : (smsSelected ? "selected" : "not_available"),
            price: smsSelected ? this.getPriceByCurrency(this.config.services['sms_broadcast']?.prices) : 0
        });
        
        // Дополнительные каналы соцсети
        const extraSocialSelected = this.state.selectedServices['extra_social']?.enabled;
        const extraSocialChannels = this.state.selectedServices['extra_social']?.channels || 0;
        modules.push({
            name: "Дополнительные каналы соцсети",
            status: extraSocialSelected ? "selected" : "not_available",
            price: extraSocialSelected ? this.getPriceByCurrency(this.config.services['extra_social']?.prices) * extraSocialChannels : 0
        });
        
        // Дополнительная воронка
        const extraFunnelSelected = this.state.selectedServices['extra_funnel']?.enabled;
        const extraFunnelChannels = this.state.selectedServices['extra_funnel']?.channels || 0;
        const extraFunnelIncluded = selectedTariff?.includedServices?.includes('extra_funnel');
        const includedExtraFunnelChannels = extraFunnelIncluded
            ? (this.getIncludedChannels(this.state.selectedTariff, 'extra_funnel') 
                || selectedTariff?.tariffFeatures?.extraFunnels?.channels 
                || 0)
            : 0;
        let extraFunnelStatus = "not_available";
        let extraFunnelPrice = 0;
        
        if (extraFunnelSelected) {
            if (extraFunnelIncluded) {
                const additionalChannels = Math.max(0, extraFunnelChannels - includedExtraFunnelChannels);
                if (additionalChannels > 0) {
                    extraFunnelStatus = "selected";
                    extraFunnelPrice = this.getPriceByCurrency(this.config.services['extra_funnel']?.prices) * additionalChannels;
                } else {
                    extraFunnelStatus = "included";
                    extraFunnelPrice = 0;
                }
            } else {
                extraFunnelStatus = "selected";
                extraFunnelPrice = this.getPriceByCurrency(this.config.services['extra_funnel']?.prices) * extraFunnelChannels;
            }
        } else if (extraFunnelIncluded) {
            extraFunnelStatus = "included";
        }
        
        modules.push({
            name: "Дополнительная воронка",
            status: extraFunnelStatus,
            price: extraFunnelPrice
        });
        
        // Разовые оплаты убраны из UI
        const oneTimeServices = [];
        
        // Формируем additional_users
        const additionalUsers = this.state.extraUsers > 0 ? {
            quantity: this.state.extraUsers,
            price_per_user: this.getExtraUserPriceByKey(this.state.selectedTariff)
        } : null;

        const contacts = this.getContactsForApiUrl();
        
        return {
            client_name: this.state.clientName,
            manager_name: this.state.managerName,
            date: date,
            
            tariff: {
                name: tariff.name,
                period_months: this.state.periodMonths,
                monthly_price: tariffPrice
            },
            
            tariff_features: tariffFeatures,
            
            additional_users: additionalUsers,
            
            modules: modules,
            
            one_time_services: oneTimeServices,
            
            contacts: {
                phone: contacts.phone,
                website: contacts.website,
                telegram: contacts.telegram
            },
            
            currency: this.getCurrencyDisplayName(),
            validity_days: 14
        };
    }
    
    async saveAndDownloadPDF() {
        if (!this.state.selectedTariff) {
            alert('Пожалуйста, выберите тариф');
            return;
        }
        
        if (!this.state.apiUrl || !this.state.token) {
            alert('Ошибка: отсутствует URL API или токен');
            return;
        }
        
        this.showLoading();
        
        try {
            // Формируем данные для API
            const pdfData = this.preparePDFData();
            if (!pdfData) {
                throw new Error('Не удалось подготовить данные для PDF');
            }
            
            // Фиксированный URL для генерации PDF
            const pdfApiUrl = 'https://billing-back.shamcrm.com/api/generateOffer';
            
            console.log('Sending PDF generation request to:', pdfApiUrl);
            console.log('PDF Data:', pdfData);
            
            // Отправляем запрос на генерацию PDF
            const response = await fetch(pdfApiUrl, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.state.token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(pdfData)
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Server error: ${response.status} - ${errorText}`);
            }
            
            // Получаем PDF blob из ответа
            const pdfBlob = await response.blob();
            
            // Скачиваем PDF
            const url = URL.createObjectURL(pdfBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `KP_${this.state.clientName}_${new Date().toISOString().slice(0, 10)}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            
            // Отправляем на сервер (для сохранения в CRM)
            await this.sendToServerWithBlob(pdfBlob);
            
            this.hideLoading();
        } catch (error) {
            console.error('Error:', error);
            this.hideLoading();
            alert('Ошибка при сохранении. Попробуйте еще раз.');
        }
    }
    
    getProposalPDFStyles() {
        return `
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            :root {
                --primary-blue: #2B4BFF;
                --dark-blue: #1a237e;
                --light-bg: #f7f6f5;
                --text-dark: #1a1a2e;
                --text-gray: #6b7280;
                --border-color: #e5e7eb;
                --icon-bg: rgba(69, 112, 255, 0.5);
            }

            body {
                font-family: Arial, sans-serif;
                background: #f7f6f5;
                color: var(--text-dark);
                line-height: 1.6;
            }

            .page {
                width: 794px;
                min-height: 1123px;
                margin: 0 auto;
                background: var(--light-bg);
                position: relative;
                overflow: hidden;
                page-break-after: always;
            }

            /* PAGE 1 - COVER */
            .cover-page {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 80px 60px 0;
            }

            .logo-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                margin-bottom: 60px;
            }

            .logo-icon {
                width: 160px;
                height: 160px;
                margin-bottom: 20px;
            }

            .logo-icon svg {
                width: 100%;
                height: 100%;
            }

            .logo-text {
                display: flex;
                align-items: center;
                font-size: 32px;
                font-weight: 700;
                letter-spacing: 2px;
            }

            .logo-text span:first-child {
                color: var(--text-dark);
            }

            .logo-text .crm-badge {
                background: var(--primary-blue);
                color: white;
                padding: 4px 12px;
                border-radius: 6px;
                margin-left: 4px;
            }

            .main-title {
                font-size: 52px;
                font-weight: 900;
                text-align: center;
                line-height: 1.2;
                margin-bottom: 80px;
                color: var(--text-dark);
            }

            .info-section {
                width: 100%;
                max-width: 400px;
            }

            .info-row {
                display: flex;
                align-items: center;
                margin-bottom: 40px;
            }

            .info-icon {
                width: 56px;
                height: 56px;
                background: var(--icon-bg);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 20px;
                flex-shrink: 0;
            }

            .info-icon svg {
                width: 28px;
                height: 28px;
                color: var(--primary-blue);
            }

            .info-label {
                font-size: 18px;
                font-weight: 500;
                color: var(--text-dark);
            }

            .cover-decoration {
                position: absolute;
                bottom: 0;
                right: 0;
                width: 100%;
                height: 150px;
                overflow: hidden;
            }

            .wave-shape {
                position: absolute;
                bottom: 0;
                right: 0;
                width: 350px;
                height: 150px;
            }

            .wave-dark {
                fill: var(--dark-blue);
            }

            .wave-light {
                fill: var(--primary-blue);
            }

            /* PAGE 2 - TARIFF */
            .tariff-page {
                padding: 40px 50px;
                position: relative;
            }

            .header-decoration {
                position: absolute;
                top: 0;
                right: 0;
                width: 300px;
                height: 80px;
                background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
                border-bottom-left-radius: 80px;
            }

            .page-header {
                display: flex;
                align-items: center;
                margin-bottom: 50px;
            }

            .tariff-header {
                display: flex;
                justify-content: flex-start;
                gap: 200px;
                margin-bottom: 30px;
            }

            .tariff-title {
                font-size: 22px;
                font-weight: 700;
                color: var(--text-dark);
            }

            .tariff-table {
                width: 100%;
                border: 2px solid var(--primary-blue);
                border-radius: 12px;
                overflow: hidden;
                margin-bottom: 40px;
            }

            .table-header {
                display: flex;
                background: white;
                border-bottom: 1px solid var(--border-color);
            }

            .table-header-cell {
                padding: 20px 24px;
                font-weight: 700;
                color: var(--primary-blue);
                font-size: 16px;
            }

            .table-header-cell:first-child {
                flex: 1;
            }

            .table-header-cell:last-child {
                width: 80px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .checkmark {
                width: 24px;
                height: 24px;
                color: var(--text-dark);
            }

            .table-row {
                display: flex;
                background: white;
                border-bottom: 1px solid var(--border-color);
            }

            .table-row:last-child {
                border-bottom: none;
            }

            .table-cell {
                padding: 20px 24px;
                font-size: 16px;
                color: var(--text-dark);
            }

            .table-cell:first-child {
                flex: 1;
            }

            .table-cell:last-child {
                width: 80px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .feature-title {
                font-weight: 500;
            }

            .feature-subtitle {
                color: var(--text-gray);
                font-size: 14px;
                margin-top: 2px;
            }

            .summary-section {
                margin-top: 40px;
            }

            .summary-row {
                font-size: 18px;
                font-weight: 700;
                color: var(--text-dark);
                margin-bottom: 16px;
            }

            .section-subtitle {
                font-size: 20px;
                font-weight: 700;
                color: var(--text-dark);
            }

            .bottom-decoration {
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                height: 100px;
            }

            .bottom-wave {
                position: absolute;
                bottom: 0;
                left: 0;
                width: 250px;
                height: 100px;
            }

            /* PAGE 3 - TOTAL COST */
            .cost-page {
                padding: 40px 50px;
                position: relative;
            }

            .cost-page .header-decoration {
                position: absolute;
                top: 0;
                right: 0;
                width: 300px;
                height: 80px;
                background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
                border-bottom-left-radius: 80px;
            }

            .section-title {
                font-size: 24px;
                font-weight: 700;
                text-align: center;
                color: var(--text-dark);
                margin-bottom: 40px;
                margin-top: 30px;
            }

            .cost-table {
                width: 100%;
                border: 2px solid var(--primary-blue);
                border-radius: 12px;
                overflow: hidden;
                margin-bottom: 60px;
            }

            .cost-table .table-header {
                display: flex;
                background: white;
                border-bottom: 1px solid var(--border-color);
            }

            .cost-table .table-header-cell {
                padding: 20px 24px;
                font-weight: 700;
                color: var(--primary-blue);
                font-size: 16px;
            }

            .cost-table .table-header-cell:first-child {
                flex: 1;
            }

            .cost-table .table-header-cell:last-child {
                width: 150px;
                text-align: right;
            }

            .cost-table .table-row {
                display: flex;
                background: white;
                border-bottom: 1px solid var(--border-color);
            }

            .cost-table .table-row:last-child {
                border-bottom: none;
            }

            .cost-table .table-cell {
                padding: 20px 24px;
                font-size: 16px;
                color: var(--text-dark);
            }

            .cost-table .table-cell:first-child {
                flex: 1;
            }

            .cost-table .table-cell:last-child {
                width: 150px;
                text-align: right;
            }

            .cost-table .table-row.total-row .table-cell {
                font-weight: 700;
                color: var(--primary-blue);
            }

            .tagline {
                text-align: center;
                margin-bottom: 40px;
            }

            .tagline-text {
                font-size: 20px;
                font-weight: 700;
                color: var(--text-dark);
                line-height: 1.5;
            }

            .tagline-text span {
                color: var(--primary-blue);
            }

            .contacts-section {
                display: flex;
                justify-content: center;
                gap: 60px;
                margin-bottom: 50px;
            }

            .contact-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .contact-icon {
                width: 56px;
                height: 56px;
                background: var(--icon-bg);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 12px;
            }

            .contact-icon svg {
                width: 26px;
                height: 26px;
                color: var(--primary-blue);
            }

            .contact-value {
                font-size: 14px;
                color: var(--text-dark);
                font-weight: 500;
            }

            .validity-notice {
                text-align: center;
                font-size: 18px;
                font-weight: 600;
                color: var(--text-dark);
            }

            .bottom-decoration-left {
                position: absolute;
                bottom: 0;
                left: 0;
                width: 250px;
                height: 120px;
            }

            .bottom-decoration-right {
                position: absolute;
                bottom: 0;
                right: 0;
                width: 250px;
                height: 120px;
            }
        `;
    }
    
    async downloadPDF() {
        const element = document.createElement('div');
        element.innerHTML = this.generatePDFContent();
        element.style.cssText = 'background: white; padding: 40px; font-family: -apple-system, BlinkMacSystemFont, sans-serif; color: #333;';
        
        const style = document.createElement('style');
        style.textContent = this.getPDFStyles();
        element.prepend(style);
        
        document.body.appendChild(element);
        
        const opt = {
            margin: 10,
            filename: `KP_${this.state.clientName}_${new Date().toISOString().slice(0, 10)}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        try {
            await html2pdf().set(opt).from(element).save();
        } catch (error) {
            console.error('PDF generation error:', error);
            throw new Error('Ошибка генерации PDF');
        } finally {
            document.body.removeChild(element);
        }
    }
    
    async generatePDFBlob() {
        const htmlContent = this.generatePDFContent();
        const element = document.createElement('div');
        element.innerHTML = htmlContent;
        element.style.cssText = 'background: #f7f6f5; font-family: Arial, sans-serif; color: #1a1a2e;';
        
        document.body.appendChild(element);
        
        const opt = {
            margin: [0, 0, 0, 0],
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#f7f6f5'
            },
            jsPDF: { 
                unit: 'px', 
                format: [794, 1123], 
                orientation: 'portrait',
                compress: true
            },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
        
        try {
            const pdfBlob = await html2pdf().set(opt).from(element).output('blob');
            document.body.removeChild(element);
            return pdfBlob;
        } catch (error) {
            document.body.removeChild(element);
            throw error;
        }
    }
    
    // ========================================
    // Send to Server
    // ========================================
    generateDescription() {
        const totals = this.calculateTotals();
        const parts = [];
        
        // Тариф
        if (this.state.selectedTariff) {
            const tariff = this.config.tariffs[this.state.selectedTariff];
            
            // Проверяем, есть ли дополнительные услуги или доп. пользователи
            let hasAdditionalServices = false;
            const selectedTariff = this.config.tariffs[this.state.selectedTariff];
            
            // Проверяем дополнительные услуги
            Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
                if (!serviceState.enabled) return;
                const service = this.config.services[key];
                if (!service || service.priceFromTariff) return;
                
                // Пропускаем интернет магазин - он в разовых оплатах
                if (key === 'online_store') return;
                
                const isIncluded = selectedTariff?.includedServices?.includes(key);
                if (!isIncluded) {
                    // Услуга не входит в тариф
                    hasAdditionalServices = true;
                } else if (service.hasChannels) {
                    // Если услуга входит в тариф, но есть дополнительные каналы
                    const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                    if (serviceState.channels > includedChannels) {
                        hasAdditionalServices = true;
                    }
                }
            });
            
            // Проверяем дополнительных пользователей
            if (this.state.extraUsers > 0) {
                hasAdditionalServices = true;
            }
            
            // Формируем текст периода
            let periodText = '6 мес';
            if (this.state.periodMonths === 12) {
                periodText = '12 мес';
            } else if (this.state.periodMonths === 8) {
                periodText = '8 мес';
            }
            
            // Формируем описание - всегда добавляем "+ Доп пакеты" если есть доп. услуги
            let description = `Тариф "${tariff.name}"`;
            if (hasAdditionalServices) {
                description += ` + Доп пакеты`;
            }
            description += ` × ${periodText}`;
            
            parts.push(description);
        }

        // Итого
        parts.push(`ИТОГО: ${this.formatTotalPrice(totals.grand)}`);
        
        return parts.join('. ');
    }
    
    async sendToServerWithBlob(pdfBlob) {
        if (!this.state.apiUrl || !this.state.token) {
            console.error('API URL or token is missing');
            alert('Ошибка: отсутствует URL API или токен');
            return;
        }
        
        const totals = this.calculateTotals();
        const currentDate = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
        
        // Создаем FormData для multipart/form-data
        const formData = new FormData();
        
        // Добавляем JSON данные как отдельные поля
        formData.append('name', this.state.clientName);
        formData.append('manager_id', parseInt(this.state.managerId) || 0);
        formData.append('lead_id', parseInt(this.state.leadId) || 0);
        formData.append('deal_status_id', parseInt(this.state.dealStatusId) || 0);
        formData.append('sum', totals.grand);
        formData.append('start_date', currentDate);
        formData.append('description', this.generateDescription());
        
        // Если есть deal_id, добавляем его в payload
        if (this.state.dealId) {
            formData.append('deal_id', this.state.dealId);
        }
        
        // Добавляем PDF файл
        // Пока отправляем реальный PDF blob (можно заменить на пустой для тестирования)
        const pdfFile = pdfBlob || new Blob([''], { type: 'application/pdf' });
        formData.append('files[]', pdfFile, `KP_${this.state.clientName}_${currentDate}.pdf`);
        
        // Формируем URL
        let apiUrl = this.state.apiUrl;
        
        // Добавляем протокол, если его нет
        if (!apiUrl.startsWith('http://') && !apiUrl.startsWith('https://')) {
            apiUrl = `https://${apiUrl}`;
        }
        
        console.log('Sending POST request to API:', apiUrl);
        console.log('FormData fields:', {
            name: this.state.clientName,
            manager_id: parseInt(this.state.managerId) || 0,
            lead_id: parseInt(this.state.leadId) || 0,
            deal_status_id: parseInt(this.state.dealStatusId) || 0,
            sum: totals.grand,
            start_date: currentDate,
            description: this.generateDescription(),
            deal_id: this.state.dealId || 'none (creating new)'
        });
        
        try {
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.state.token}`
                },
                body: formData
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Server error: ${response.status} - ${errorText}`);
            }
            
            const responseData = await response.json().catch(() => ({ success: true }));
            console.log('Server response:', responseData);
            
            this.showSuccessModal();
        } catch (error) {
            console.error('Error sending to server:', error);
            throw error;
        }
    }
    
    // ========================================
    // UI Helpers
    // ========================================
    showLoading() {
        document.getElementById('loadingOverlay').classList.add('active');
    }
    
    hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('active');
    }
    
    showSuccessModal() {
        document.getElementById('successModal').classList.add('active');
    }
    
    closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
}

// Initialize app
document.addEventListener('DOMContentLoaded', () => {
    window.cpGenerator = new CPGenerator();
});
