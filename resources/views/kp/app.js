// ========================================
// CRM Commercial Proposal Generator
// ========================================

class CPGenerator {
    constructor() {
        this.config = null;
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
            clientEmail: '',
            emailVerificationStatus: 'idle',
            emailVerificationMessage: '',
            emailVerifyHttpStatus: null,
            managerId: '',
            managerName: '',
            token: '',
            csrfToken: '',
            saveUrl: '/application/kp/store',
            createClientUrl: 'https://billing-back.shamcrm.com/api/sendRequest',
            generateOfferUrl: 'https://billing-back.shamcrm.com/api/generateOffer',
            leadId: '',
            dealStatusId: '',
            apiUrl: '',
            dealId: '',
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
        this.renderAll();
        this.bindEvents();
    }

    // ========================================
    // ИЗМЕНЕНО: читаем из window.CP_CONFIG вместо fetch
    // ========================================
    async loadConfig() {
        if (window.CP_CONFIG) {
            this.config = window.CP_CONFIG;
        } else {
            // fallback — пробуем загрузить config.json
            try {
                const configUrl = window.CP_CONFIG_URL || 'data/config.json';
                const response = await fetch(configUrl);
                this.config = await response.json();
            } catch (error) {
                console.error('Failed to load config:', error);
                this.config = this.getDefaultConfig();
            }
        }

        // Устанавливаем дефолтную валюту — первая из списка
        if (this.config.currencies) {
            const firstCurrency = Object.keys(this.config.currencies)[0];
            if (firstCurrency) {
                this.state.currency = firstCurrency;
            }
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

    // ========================================
    // ИЗМЕНЕНО: читаем из window.CP_META вместо URL params
    // ========================================
    parseQueryParams() {
        const meta = window.CP_META || {};

        this.state.managerName      = meta.managerName      || '';
        this.state.managerId        = meta.managerId        || '';
        this.state.csrfToken        = meta.csrfToken        || '';
        this.state.saveUrl          = meta.saveUrl          || '/application/kp/store';
        this.state.createClientUrl  = meta.createClientUrl  || 'https://billing-back.shamcrm.com/api/sendRequest';
        this.state.generateOfferUrl = meta.generateOfferUrl || 'https://billing-back.shamcrm.com/api/generateOffer';
        this.state.leadId           = meta.leadId           || '';
        this.state.dealStatusId     = meta.dealStatusId     || '';
        this.state.apiUrl           = meta.apiUrl           || '';
        this.state.dealId           = meta.dealId           || '';
        this.state.token            = meta.token            || '';
    }

    getApiHost() {
        if (!this.state.apiUrl) return '';
        const rawUrl = this.state.apiUrl.trim();
        if (!rawUrl) return '';
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
            return { ...defaultContacts, phone: '+992488885050', telegram: '@Mrashuraliev' };
        }

        if (apiHost === 'fingroupcrm-back.shamcrm.com') {
            return { ...defaultContacts, phone: '+992488886363', telegram: '@FINGROUPCRM' };
        }

        return defaultContacts;
    }

    renderAll() {
        this.renderCurrencies(); // НОВОЕ
        this.renderTariffs();
        this.renderServices();
        this.updateExtraUsersSection();
        this.updateOneTimePayments();
        this.updateSummary();
    }

    // ========================================
    // НОВОЕ: рендер кнопок валют динамически из config
    // ========================================
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
            const price = this.getTariffPrice(tariff);
            const originalPrice = this.getOriginalPrice(tariff);
            const isPopular = index === popularIndex;
            const isSelected = this.state.selectedTariff === key;

            const card = document.createElement('div');
            card.className = `tariff-card${isPopular ? ' popular' : ''}${isSelected ? ' selected' : ''}`;
            card.dataset.tariff = key;

            card.innerHTML = `
                <div class="tariff-select-indicator" style="margin-bottom: 250px !important;"></div>
                <div class="tariff-header">
                    <h3 class="tariff-name">${tariff.name}</h3>
                </div>
                <div class="tariff-price">
                    <span class="price-value">${this.formatPrice(price)}</span>
                    <span class="price-period">/мес</span>
                    ${(this.state.periodMonths === 12 || this.state.periodMonths === 8)
                ? `<span class="original-price">${this.formatPrice(originalPrice)}</span>`
                : ''}
                </div>
            `;

            card.addEventListener('click', () => this.selectTariff(key));
            grid.appendChild(card);
        });
    }

    getIncludedChannels(tariffKey, serviceKey) {
        if (!tariffKey || !serviceKey) return 0;

        const tariff = this.config.tariffs[tariffKey];
        const tf = tariff?.tariffFeatures || {};

        if (serviceKey === 'telegram_b2c') return tf.miniAppB2C?.channels || 0;
        if (serviceKey === 'telegram_b2b') return tf.miniAppB2B?.channels || 0;
        if (serviceKey === 'extra_funnel')  return tf.extraFunnels?.channels || 0;

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
                this.formatPrice(tariff.extraUserPrice[this.state.currency]);
        }
    }

    selectTariff(tariffKey) {
        this.state.previousTariff = this.state.selectedTariff;
        this.state.selectedTariff = tariffKey;
        this.state.extraUsers = 0;

        const tariff = this.config.tariffs[tariffKey];
        const extraUsersSection = document.getElementById('extraUsersSection');
        extraUsersSection.style.display = 'block';

        document.getElementById('includedUsers').textContent = tariff.users;
        document.getElementById('extraUserPrice').textContent =
            this.formatPrice(tariff.extraUserPrice[this.state.currency]);
        document.getElementById('extraUsersInput').value = 0;

        if (this.state.previousTariff && this.state.previousTariff !== tariffKey) {
            const oldTariff = this.config.tariffs[this.state.previousTariff];
            if (oldTariff && oldTariff.suggestedImplementationPrice) {
                const oldSuggestedPrice = oldTariff.suggestedImplementationPrice[this.state.currency];
                if (Math.abs(this.state.implementationPrice - oldSuggestedPrice) < 0.01) {
                    const newSuggestedPrice = tariff.suggestedImplementationPrice?.[this.state.currency] || 0;
                    if (newSuggestedPrice > 0) {
                        this.state.implementationPrice = newSuggestedPrice;
                        this.state.implementationPriceWasAutoSet = true;
                    }
                }
            }
        } else {
            const suggestedPrice = tariff.suggestedImplementationPrice?.[this.state.currency] || 0;
            if (suggestedPrice > 0) {
                this.state.implementationPrice = suggestedPrice;
                this.state.implementationPriceWasAutoSet = true;
            }
        }

        const implHint  = document.getElementById('implementationHint');
        const implInput = document.getElementById('implementationPrice');
        if (tariff.suggestedImplementationPrice) {
            const suggestedPrice = tariff.suggestedImplementationPrice[this.state.currency];
            if (suggestedPrice) {
                if (implHint)  implHint.textContent = `${this.formatPrice(suggestedPrice)} (скидка до 50 процентов)`;
                if (implInput) implInput.placeholder = suggestedPrice.toString();
            }
        }

        Object.keys(this.state.selectedServices).forEach(serviceKey => {
            const service = this.config.services[serviceKey];
            if (service && service.hasChannels) {
                this.state.selectedServices[serviceKey] = { enabled: false, channels: 1 };
            } else if (this.state.selectedServices[serviceKey]) {
                this.state.selectedServices[serviceKey].enabled = false;
            }
        });

        this.state.onlineStorePrice = 0;

        if (tariff.includedServices) {
            tariff.includedServices.forEach(serviceKey => {
                if (serviceKey === 'ip_telephony') return;
                const service = this.config.services[serviceKey];
                const includedChannels = this.getIncludedChannels(tariffKey, serviceKey);

                if (service && service.hasChannels) {
                    this.state.selectedServices[serviceKey] = { enabled: true, channels: includedChannels || 1 };
                } else {
                    this.state.selectedServices[serviceKey] = { enabled: true, channels: 1 };
                }
            });
        }

        if (tariffKey === 'premium' || tariffKey === 'vip') {
            if (!this.state.selectedServices['sms_broadcast']) {
                this.state.selectedServices['sms_broadcast'] = { enabled: true, channels: 1 };
            } else {
                this.state.selectedServices['sms_broadcast'].enabled = true;
            }
        }

        this.renderServices();
        this.renderTariffs();
        this.updateOneTimePayments();
        this.updateSummary();
    }

    getTariffPrice(tariff) {
        if (this.state.periodMonths === 12 && tariff.prices12Months) {
            return tariff.prices12Months[this.state.currency] || 0;
        }
        if (this.state.periodMonths === 8) {
            return (tariff.prices[this.state.currency] || 0) * 0.5;
        }
        return tariff.prices[this.state.currency] || 0;
    }

    getOriginalPrice(tariff) {
        return tariff.prices[this.state.currency] || 0;
    }

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
            if (service.priceFromTariff || key === 'ip_telephony') return;

            const isIncluded = selectedTariff?.includedServices?.includes(key);
            const isSelected = this.state.selectedServices[key]?.enabled;
            const channels   = this.state.selectedServices[key]?.channels || (isIncluded ? 1 : 1);

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
                        <p>${service.description || ''}</p>
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
                const wasEnabled = this.state.selectedServices[serviceKey].enabled;
                this.state.selectedServices[serviceKey].enabled = e.target.checked;

                if (serviceKey === 'online_store' && wasEnabled && !e.target.checked) {
                    this.state.onlineStorePrice = 0;
                    const onlineStoreInput = document.getElementById('onlineStorePrice');
                    if (onlineStoreInput) onlineStoreInput.value = '0';
                }

                this.renderServices();
                this.updateOneTimePayments();
                this.updateSummary();
            });
        });

        document.querySelectorAll('.channels-control .qty-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const serviceKey = e.target.dataset.service;
                const action     = e.target.dataset.action;

                if (!this.state.selectedServices[serviceKey]) {
                    this.state.selectedServices[serviceKey] = { enabled: false, channels: 1 };
                }

                const selectedTariff = this.state.selectedTariff
                    ? this.config.tariffs[this.state.selectedTariff]
                    : null;
                const isIncluded  = selectedTariff?.includedServices?.includes(serviceKey);
                const minChannels = isIncluded ? this.getIncludedChannels(this.state.selectedTariff, serviceKey) : 1;

                if (action === 'increase') {
                    this.state.selectedServices[serviceKey].channels++;
                } else if (action === 'decrease' && this.state.selectedServices[serviceKey].channels > minChannels) {
                    this.state.selectedServices[serviceKey].channels--;
                }

                this.renderServices();
                this.updateOneTimePayments();
                this.updateSummary();
            });
        });

        document.querySelectorAll('.channels-control .qty-input').forEach(input => {
            input.addEventListener('change', (e) => {
                const serviceKey = e.target.dataset.service;
                const selectedTariff = this.state.selectedTariff
                    ? this.config.tariffs[this.state.selectedTariff]
                    : null;
                const isIncluded  = selectedTariff?.includedServices?.includes(serviceKey);
                const minChannels = isIncluded ? this.getIncludedChannels(this.state.selectedTariff, serviceKey) : 1;
                const value = Math.max(minChannels, parseInt(e.target.value) || minChannels);

                if (!this.state.selectedServices[serviceKey]) {
                    this.state.selectedServices[serviceKey] = { enabled: false, channels: value };
                } else {
                    this.state.selectedServices[serviceKey].channels = value;
                }

                this.renderServices();
                this.updateOneTimePayments();
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
        return;
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
                        <span class="one-time-currency">${this.config.currencies[this.state.currency].symbol}</span>
                        <button class="remove-custom-service-btn" data-index="${index}" title="Удалить">×</button>
                    </div>
                </div>
            `;
            container.appendChild(item);
        });

        this.bindCustomServiceEvents();
    }

    bindCustomServiceEvents() {
        document.querySelectorAll('.custom-service-name-input').forEach(input => {
            input.addEventListener('input', (e) => {
                const index = parseInt(e.target.dataset.index);
                if (this.state.customOneTimePayments[index]) {
                    this.state.customOneTimePayments[index].name = e.target.value;
                    this.updateSummary();
                }
            });
        });

        document.querySelectorAll('.custom-service-price-input').forEach(input => {
            input.addEventListener('input', (e) => {
                const index = parseInt(e.target.dataset.index);
                if (this.state.customOneTimePayments[index]) {
                    this.state.customOneTimePayments[index].price = parseFloat(e.target.value) || 0;
                    this.updateSummary();
                }
            });
        });

        document.querySelectorAll('.remove-custom-service-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.target.dataset.index);
                this.removeCustomOneTimePayment(index);
            });
        });
    }

    addCustomOneTimePayment() {
        this.state.customOneTimePayments.push({ name: '', price: 0 });
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

        let monthlyTotal = 0;
        const monthlyPayments = [];

        if (this.state.selectedTariff) {
            const tariff     = this.config.tariffs[this.state.selectedTariff];
            const tariffPrice = this.getTariffPrice(tariff);
            monthlyTotal += tariffPrice;

            monthlyPayments.push({
                name: `Тариф "${tariff.name}"`,
                pricePerMonth: tariffPrice
            });

            if (this.state.extraUsers > 0) {
                const extraUserPrice = tariff.extraUserPrice[this.state.currency] * this.state.extraUsers;
                monthlyTotal += extraUserPrice;
                monthlyPayments.push({
                    name: `Доп. пользователи (×${this.state.extraUsers})`,
                    pricePerMonth: extraUserPrice
                });
            }
        }

        const selectedTariff = this.state.selectedTariff
            ? this.config.tariffs[this.state.selectedTariff]
            : null;

        Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
            if (!serviceState.enabled) return;
            if (key === 'ip_telephony') return;

            const service = this.config.services[key];
            if (!service || service.priceFromTariff) return;

            const isIncluded = selectedTariff?.includedServices?.includes(key);

            if (key === 'online_store') {
                const monthlyPrice = service.prices[this.state.currency];
                monthlyTotal += monthlyPrice;
                monthlyPayments.push({ name: 'Интернет магазин', pricePerMonth: monthlyPrice });
                return;
            }

            const basePrice = service.prices[this.state.currency];
            const channels  = service.hasChannels ? serviceState.channels : 1;
            let totalPrice  = 0;
            let displayChannels = channels;

            if (isIncluded && service.hasChannels) {
                const includedChannels    = this.getIncludedChannels(this.state.selectedTariff, key);
                const additionalChannels  = channels - includedChannels;
                if (additionalChannels > 0) {
                    totalPrice = basePrice * additionalChannels;
                    displayChannels = additionalChannels;
                } else {
                    return;
                }
            } else if (isIncluded) {
                return;
            } else {
                totalPrice = basePrice * channels;
            }

            if (service.type !== 'one_time') {
                monthlyTotal += totalPrice;

                let displayText = service.name;
                if (isIncluded && service.hasChannels) {
                    const includedChannels = this.getIncludedChannels(this.state.selectedTariff, key);
                    displayText = displayChannels > 0
                        ? `${service.name} (${includedChannels} ${includedChannels === 1 ? 'канал включен' : 'канала включено'}, +${displayChannels} доп.)`
                        : `${service.name} (${includedChannels} ${includedChannels === 1 ? 'канал включен' : 'канала включено'})`;
                } else if (channels > 1) {
                    displayText = `${service.name} (×${channels})`;
                }

                monthlyPayments.push({ name: displayText, pricePerMonth: totalPrice });
            }
        });

        if (monthlyPayments.length > 0) {
            let tableHTML = '<table class="payments-table">';
            tableHTML += '<thead>';
            tableHTML += '<tr><th colspan="3" class="section-header">Месячные платежи</th></tr>';
            tableHTML += '<tr><th>Название услуги/тарифа</th><th>Цена в месяц</th><th>Сумма</th></tr>';
            tableHTML += '</thead><tbody>';

            monthlyPayments.forEach(payment => {
                const total = payment.pricePerMonth * this.state.periodMonths;
                tableHTML += `
                    <tr>
                        <td>${payment.name}</td>
                        <td>${this.formatPrice(payment.pricePerMonth)}</td>
                        <td>${this.formatPrice(total)}</td>
                    </tr>
                `;
            });

            tableHTML += '</tbody></table>';
            summaryItems.innerHTML += tableHTML;
        }

        const periodTotal = monthlyTotal * this.state.periodMonths;
        const grandTotal  = periodTotal;

        const periodDetailsEl = document.getElementById('periodDetails');
        if (periodDetailsEl && this.state.selectedTariff) {
            const tariff = this.config.tariffs[this.state.selectedTariff];

            let hasAdditionalServices = false;
            if (this.state.extraUsers > 0) hasAdditionalServices = true;

            Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
                if (!serviceState.enabled) return;
                const service = this.config.services[key];
                if (!service || service.priceFromTariff) return;
                if (key === 'ip_telephony' || key === 'online_store') return;

                const isIncluded = selectedTariff?.includedServices?.includes(key);
                if (!isIncluded) {
                    hasAdditionalServices = true;
                } else if (service.hasChannels && serviceState.channels > 1) {
                    hasAdditionalServices = true;
                }
            });

            let detailsText = `Тариф "${tariff.name}"`;
            if (hasAdditionalServices) detailsText += ` + Доп пакеты`;

            if (this.state.periodMonths === 12) {
                detailsText += ` × ${this.state.periodMonths} мес (скидка 15%)`;
            } else if (this.state.periodMonths === 8) {
                detailsText += ` × ${this.state.periodMonths} мес (скидка 50%)`;
            } else {
                detailsText += ` × ${this.state.periodMonths} мес`;
            }

            periodDetailsEl.innerHTML = detailsText;
        } else if (periodDetailsEl) {
            periodDetailsEl.innerHTML = '';
        }

        const periodMonthlyTotalEl = document.getElementById('periodMonthlyTotal');
        if (periodMonthlyTotalEl) {
            periodMonthlyTotalEl.textContent = monthlyTotal > 0 ? this.formatTotalPrice(monthlyTotal) : '';
        }

        document.getElementById('periodTotal').textContent = this.formatTotalPrice(periodTotal);
        const oneTimeTotalEl = document.getElementById('oneTimeTotal');
        if (oneTimeTotalEl) oneTimeTotalEl.textContent = this.formatTotalPrice(0);
        document.getElementById('grandTotal').textContent = this.formatTotalPrice(grandTotal);

        if (summaryItems.innerHTML === '') {
            summaryItems.innerHTML = `
                <div class="summary-item" style="justify-content: center; color: var(--text-muted);">
                    Выберите тариф для расчёта
                </div>
            `;
        }
    }

    formatPrice(amount) {
        const currency = this.config.currencies[this.state.currency];
        if (!currency) return amount;

        if (this.state.currency === 'USD') {
            return `$${amount.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        }

        const formatted = amount.toLocaleString('ru-RU', {
            minimumFractionDigits: amount % 1 === 0 ? 0 : 2,
            maximumFractionDigits: 2
        });
        return `${formatted} ${currency.symbol}`;
    }

    formatTotalPrice(amount) {
        const currency = this.config.currencies[this.state.currency];
        if (!currency) return amount;
        const rounded = Math.round(amount);

        if (this.state.currency === 'USD') {
            return `$${rounded.toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
        }
        return `${rounded.toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} ${currency.symbol}`;
    }

    getCurrencyDisplayName() {
        const currencyMap = { 'TJS': 'сомони', 'UZS': 'сум', 'USD': '$', 'EUR': '€', 'RUB': 'руб.' };
        return currencyMap[this.state.currency] || this.state.currency;
    }

    formatNumberInput(value) {
        if (!value && value !== 0) return '';
        const num = parseFloat(value.toString().replace(/\s/g, '').replace(/,/g, '.')) || 0;
        return num.toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 2, useGrouping: true });
    }

    parseNumberInput(value) {
        if (!value) return 0;
        const cleaned = value.toString().replace(/\s/g, '').replace(/\u00A0/g, '').replace(/,/g, '.');
        return parseFloat(cleaned) || 0;
    }

    isEmailFormatValid(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    setEmailVerificationState(status, message = '') {
        this.state.emailVerificationStatus  = status;
        this.state.emailVerificationMessage = message;

        const statusEl   = document.getElementById('clientEmailStatus');
        const createBtn  = document.getElementById('createClientBtn');
        if (!statusEl) return;

        statusEl.classList.remove('success', 'error', 'pending');
        statusEl.style.color = '';
        if (createBtn) createBtn.style.display = 'none';

        if (status === 'success') {
            statusEl.classList.add('success');
            statusEl.style.color = '#198754';
            statusEl.textContent = message || '✓ Клиент успешно найден';
        } else if (status === 'error') {
            statusEl.classList.add('error');
            statusEl.style.color = '#dc3545';
            statusEl.textContent = message || 'Ошибка проверки email';
            if (createBtn && this.state.emailVerifyHttpStatus === 400) {
                createBtn.style.display = 'inline-flex';
            }
        } else if (status === 'pending') {
            statusEl.classList.add('pending');
            statusEl.style.color = '#6c757d';
            statusEl.textContent = message || 'Проверка email...';
        } else {
            statusEl.textContent = '';
        }
    }

    openCreateClientModal() {
        const emailInput = document.getElementById('newClientEmail');
        const statusEl   = document.getElementById('newClientStatus');
        if (emailInput) emailInput.value = this.state.clientEmail || '';
        if (statusEl)   { statusEl.textContent = ''; statusEl.className = 'client-email-status'; }
        document.getElementById('createClientModal')?.classList.add('active');
    }

    closeCreateClientModal() {
        document.getElementById('createClientModal')?.classList.remove('active');
    }

    async submitCreateClient() {
        const name     = document.getElementById('newClientName')?.value?.trim()  || '';
        const phone    = document.getElementById('newClientPhone')?.value?.trim() || '';
        const email    = document.getElementById('newClientEmail')?.value?.trim() || '';
        const statusEl = document.getElementById('newClientStatus');

        if (!name || !phone || !email) {
            if (statusEl) { statusEl.className = 'client-email-status error'; statusEl.textContent = 'Заполните все поля'; }
            return;
        }

        if (!this.isEmailFormatValid(email)) {
            if (statusEl) { statusEl.className = 'client-email-status error'; statusEl.textContent = 'Введите корректный email'; }
            return;
        }

        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (this.state.token)     headers.Authorization  = `Bearer ${this.state.token}`;
        if (this.state.csrfToken) headers['X-CSRF-TOKEN'] = this.state.csrfToken;

        try {
            if (statusEl) { statusEl.className = 'client-email-status pending'; statusEl.textContent = 'Сохраняем...'; }

            const response = await fetch(this.state.createClientUrl || 'https://billing-back.shamcrm.com/api/sendRequest', {
                method: 'POST',
                headers,
                body: JSON.stringify({ fio: name, phone, email, request_type: 'demo', partner_id: 1 }),
            });

            const responseText = await response.text();
            let payload = null;
            try { payload = responseText ? JSON.parse(responseText) : null; } catch (_) {}

            if (!response.ok) {
                const errorMessage = payload?.message || `Ошибка сохранения (${response.status})`;
                if (statusEl) { statusEl.className = 'client-email-status error'; statusEl.textContent = errorMessage; }
                return;
            }

            this.state.clientEmail = email;
            this.state.emailVerifyHttpStatus = 200;
            this.setEmailVerificationState('success');
            this.closeCreateClientModal();
        } catch (error) {
            if (statusEl) { statusEl.className = 'client-email-status error'; statusEl.textContent = 'Не удалось сохранить клиента'; }
        }
    }

    // ========================================
    // Bind Events
    // ========================================
    bindEvents() {

        // ========================================
        // ИЗМЕНЕНО: select клиентов вместо input email
        // ========================================
        const clientSelect = document.getElementById('clientEmailInput');
        if (clientSelect) {
            clientSelect.addEventListener('change', (e) => {
                const opt = e.target.selectedOptions[0];
                this.state.clientEmail = opt.value;
                this.state.clientName  = opt.dataset.name  || '';
                this.state.clientPhone = opt.dataset.phone || '';

                if (opt.value) {
                    this.setEmailVerificationState('success', `✓ Выбран: ${opt.dataset.name}`);
                } else {
                    this.setEmailVerificationState('idle');
                }

                // Автопереключение валюты клиента
                const currency = opt.dataset.currency;
                if (currency && this.config.currencies[currency]) {
                    this.state.currency = currency;
                    this.renderAll();
                }
            });
        }

        const createClientBtn = document.getElementById('createClientBtn');
        if (createClientBtn) createClientBtn.addEventListener('click', () => this.openCreateClientModal());

        document.getElementById('closeCreateClientModalBtn')?.addEventListener('click',  () => this.closeCreateClientModal());
        document.getElementById('cancelCreateClientBtn')?.addEventListener('click',      () => this.closeCreateClientModal());
        document.getElementById('saveCreateClientBtn')?.addEventListener('click',        () => this.submitCreateClient());

        // ========================================
        // ИЗМЕНЕНО: currency кнопки — делегирование через currencySelector
        // ========================================
        const currencySelector = document.getElementById('currencySelector');
        if (currencySelector) {
            currencySelector.addEventListener('click', (e) => {
                const btn = e.target.closest('.currency-btn');
                if (!btn) return;

                const oldCurrency        = this.state.currency;
                const oldSuggestedPrice  = this.getSuggestedImplementationPrice(oldCurrency);

                if (this.state.implementationPriceWasAutoSet &&
                    Math.abs(this.state.implementationPrice - oldSuggestedPrice) < 0.01) {
                    this.state.previousCurrency      = oldCurrency;
                    this.state.previousSuggestedPrice = oldSuggestedPrice;
                    this.state.implementationPriceWasAutoSet = true;
                } else {
                    this.state.implementationPriceWasAutoSet = false;
                }

                this.state.currency = btn.dataset.currency;
                this.renderAll();
            });
        }

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

        const implPriceInput = document.getElementById('implementationPrice');
        if (implPriceInput) {
            implPriceInput.addEventListener('input', (e) => {
                const input          = e.target;
                const cursorPosition = input.selectionStart;
                const oldValue       = input.value;

                const parsedValue = this.parseNumberInput(input.value);
                this.state.implementationPrice = Math.max(0, parsedValue);
                this.state.implementationPriceWasAutoSet = false;

                const formattedValue = this.formatNumberInput(this.state.implementationPrice);
                input.value = formattedValue;

                const lengthDiff = formattedValue.length - oldValue.length;
                let newCursorPosition = cursorPosition >= oldValue.length
                    ? formattedValue.length
                    : Math.max(0, Math.min(cursorPosition + lengthDiff, formattedValue.length));
                input.setSelectionRange(newCursorPosition, newCursorPosition);

                this.updateSummary();
            });

            implPriceInput.addEventListener('blur', (e) => {
                const input   = e.target;
                const parsed  = this.parseNumberInput(input.value);
                this.state.implementationPrice = Math.max(0, parsed);
                input.value = this.formatNumberInput(this.state.implementationPrice);
                this.updateSummary();
            });
        }

        const onlineStorePriceInput = document.getElementById('onlineStorePrice');
        if (onlineStorePriceInput) {
            onlineStorePriceInput.addEventListener('input', (e) => {
                const input          = e.target;
                const cursorPosition = input.selectionStart;
                const oldValue       = input.value;

                const parsedValue = this.parseNumberInput(input.value);
                this.state.onlineStorePrice = Math.max(0, parsedValue);

                const formattedValue = this.formatNumberInput(this.state.onlineStorePrice);
                input.value = formattedValue;

                const lengthDiff = formattedValue.length - oldValue.length;
                let newCursorPosition = cursorPosition >= oldValue.length
                    ? formattedValue.length
                    : Math.max(0, Math.min(cursorPosition + lengthDiff, formattedValue.length));
                input.setSelectionRange(newCursorPosition, newCursorPosition);

                this.updateSummary();
            });

            onlineStorePriceInput.addEventListener('blur', (e) => {
                const input  = e.target;
                const parsed = this.parseNumberInput(input.value);
                this.state.onlineStorePrice = Math.max(0, parsed);
                input.value = this.formatNumberInput(this.state.onlineStorePrice);
                this.updateSummary();
            });
        }

        document.getElementById('saveBtn').addEventListener('click', () => this.saveAndDownloadPDF());
        document.getElementById('closeSuccessBtn').addEventListener('click', () => this.closeModal('successModal'));

        const addCustomServiceBtn = document.getElementById('addCustomServiceBtn');
        if (addCustomServiceBtn) addCustomServiceBtn.addEventListener('click', () => this.addCustomOneTimePayment());

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.remove('active');
            });
        });
    }

    // ========================================
    // PDF Generation
    // ========================================
    generatePDFContent() {
        const date     = new Date().toLocaleDateString('ru-RU');
        let periodText = '6 месяцев';
        if (this.state.periodMonths === 12) periodText = '12 месяцев (скидка 15%)';
        else if (this.state.periodMonths === 8) periodText = '8 месяцев (скидка 50%)';

        if (!this.state.selectedTariff) return '<div>Выберите тариф для генерации КП</div>';

        const tariff     = this.config.tariffs[this.state.selectedTariff];
        const tariffPrice = this.getTariffPrice(tariff);

        const additionalPackages = this.getAdditionalPackages();
        const oneTimePayments    = this.getOneTimePayments();
        const totals             = this.calculateTotals();
        const contacts           = this.getContactsForApiUrl();

        let html = `
            <style>${this.getProposalPDFStyles()}</style>
            <div class="page cover-page">
                <div class="logo-container">
                    <div class="logo-icon">
                        <svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="160" height="160" rx="20" fill="#2B4BFF"/>
                            <text x="80" y="100" font-family="Arial, sans-serif" font-size="60" font-weight="bold" fill="white" text-anchor="middle">CRM</text>
                        </svg>
                    </div>
                    <div class="logo-text"><span>SHAM</span><span class="crm-badge">CRM</span></div>
                </div>
                <h1 class="main-title">КОММЕРЧЕСКОЕ<br>ПРЕДЛОЖЕНИЕ</h1>
                <div class="info-section">
                    <div class="info-row">
                        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                        <span class="info-label">Клиент: ${this.state.clientName}</span>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                        <span class="info-label">Менеджер: ${this.state.managerName}</span>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
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

            <div class="page tariff-page">
                <div class="header-decoration"></div>
                <div class="page-header">
                    <div class="logo-text"><span>SHAM</span><span class="crm-badge">CRM</span></div>
                </div>
                <div class="tariff-header">
                    <span class="tariff-title">Тариф shamCRM: <strong>${tariff.name}</strong></span>
                    <span class="tariff-title">Срок: <strong>${periodText}</strong></span>
                </div>
                <div class="tariff-table">
                    <div class="table-header">
                        <div class="table-header-cell">Входит в тариф</div>
                        <div class="table-header-cell"><svg class="checkmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                    </div>
                    ${(tariff.features || []).map(feature => `
                        <div class="table-row">
                            <div class="table-cell"><div class="feature-title">${feature}</div></div>
                            <div class="table-cell"><svg class="checkmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                        </div>
                    `).join('')}
                </div>
                <div class="summary-section">
                    <div class="summary-row">Пользователей в тарифе: <strong>${tariff.users}</strong></div>
                    <div class="summary-row">Стоимость тарифа: <strong>${this.formatPrice(tariffPrice)}/мес</strong></div>
                    ${this.state.extraUsers > 0 ? `
                        <div class="summary-row">Доп. пользователи: <strong>${this.state.extraUsers} × ${this.formatPrice(tariff.extraUserPrice[this.state.currency])} = ${this.formatPrice(tariff.extraUserPrice[this.state.currency] * this.state.extraUsers)}/мес</strong></div>
                    ` : ''}
                </div>
                ${additionalPackages.length > 0 ? `
                    <h3 class="section-subtitle" style="margin-top: 40px; margin-bottom: 20px;">Дополнительные пакеты</h3>
                    <div class="tariff-table">
                        <div class="table-header">
                            <div class="table-header-cell">Название пакета</div>
                            <div class="table-header-cell" style="width: 150px; text-align: right;">Стоимость/мес</div>
                        </div>
                        ${additionalPackages.map(pkg => `
                            <div class="table-row">
                                <div class="table-cell"><div class="feature-title">${pkg.name}</div>${pkg.description ? `<div class="feature-subtitle">${pkg.description}</div>` : ''}</div>
                                <div class="table-cell" style="text-align: right; font-weight: 600;">${this.formatPrice(pkg.price)}</div>
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

            <div class="page cost-page">
                <div class="header-decoration"></div>
                <div class="page-header">
                    <div class="logo-text"><span>SHAM</span><span class="crm-badge">CRM</span></div>
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
                            <div class="table-cell">${this.formatTotalPrice(tariff.extraUserPrice[this.state.currency] * this.state.extraUsers * this.state.periodMonths)}</div>
                        </div>
                    ` : ''}
                    ${additionalPackages.map(pkg => `
                        <div class="table-row">
                            <div class="table-cell">${pkg.name} (${this.state.periodMonths} мес.)</div>
                            <div class="table-cell">${this.formatTotalPrice(pkg.price * this.state.periodMonths)}</div>
                        </div>
                    `).join('')}
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
                        <div class="contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
                        <span class="contact-value">${contacts.phone}</span>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
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
        const selectedTariff = this.state.selectedTariff ? this.config.tariffs[this.state.selectedTariff] : null;

        Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
            if (!serviceState.enabled) return;
            if (key === 'ip_telephony' || key === 'online_store') return;

            const service = this.config.services[key];
            if (!service || service.priceFromTariff) return;

            const isIncluded = selectedTariff?.includedServices?.includes(key);

            if (isIncluded) {
                if (service.hasChannels) {
                    const includedChannels   = this.getIncludedChannels(this.state.selectedTariff, key);
                    const additionalChannels = serviceState.channels - includedChannels;
                    if (additionalChannels > 0) {
                        packages.push({
                            name: `${service.name} (доп. ×${additionalChannels})`,
                            description: service.description,
                            price: service.prices[this.state.currency] * additionalChannels
                        });
                    }
                }
                return;
            }

            if (service.type !== 'one_time') {
                const channels = service.hasChannels ? serviceState.channels : 1;
                packages.push({
                    name: service.name + (channels > 1 ? ` (×${channels})` : ''),
                    description: service.description,
                    price: service.prices[this.state.currency] * channels
                });
            }
        });

        return packages;
    }

    getOneTimePayments() {
        return [];
    }

    getSelectedServicesForPDF() {
        const services = [];
        const selectedTariff = this.state.selectedTariff ? this.config.tariffs[this.state.selectedTariff] : null;

        if (this.state.selectedTariff && this.state.extraUsers > 0) {
            const tariff = this.config.tariffs[this.state.selectedTariff];
            services.push({
                key: 'extra_user',
                name: `Дополнительные пользователи (×${this.state.extraUsers})`,
                type: 'monthly',
                quantity: this.state.extraUsers,
                unit_price: tariff.extraUserPrice[this.state.currency],
                price: tariff.extraUserPrice[this.state.currency] * this.state.extraUsers,
                included: false,
                status: 'selected'
            });
        }

        Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
            if (!serviceState.enabled) return;

            const service = this.config.services[key];
            if (!service || service.priceFromTariff) return;

            const isIncluded = selectedTariff?.includedServices?.includes(key);

            if (key === 'online_store') {
                const monthlyPrice = service.prices[this.state.currency];
                services.push({
                    key, name: 'Интернет магазин', type: 'monthly',
                    quantity: 1, unit_price: monthlyPrice, price: monthlyPrice,
                    included: false, status: 'selected'
                });
                return;
            }

            if (isIncluded) {
                if (service.hasChannels) {
                    const channels           = serviceState.channels || 1;
                    const additionalChannels = channels - 1;
                    if (additionalChannels > 0) {
                        const totalPrice = service.prices[this.state.currency] * additionalChannels;
                        services.push({
                            key, name: `${service.name} (доп. ×${additionalChannels})`,
                            type: service.type === 'one_time' ? 'one_time' : 'monthly',
                            quantity: additionalChannels, unit_price: service.prices[this.state.currency],
                            price: totalPrice, included: false, status: 'selected'
                        });
                    }
                }
                return;
            }

            const basePrice  = service.prices[this.state.currency];
            const channels   = service.hasChannels ? serviceState.channels : 1;
            let name         = service.name;
            if (channels > 1) name = `${service.name} (×${channels})`;

            services.push({
                key, name,
                type: service.type === 'one_time' ? 'one_time' : 'monthly',
                quantity: channels, unit_price: basePrice,
                price: basePrice * channels, included: false, status: 'selected'
            });
        });

        return services;
    }

    calculateTotals() {
        let monthly = 0;

        if (this.state.selectedTariff) {
            const tariff = this.config.tariffs[this.state.selectedTariff];
            monthly += this.getTariffPrice(tariff);
            if (this.state.extraUsers > 0) {
                monthly += tariff.extraUserPrice[this.state.currency] * this.state.extraUsers;
            }
        }

        const selectedTariff = this.state.selectedTariff ? this.config.tariffs[this.state.selectedTariff] : null;

        Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
            if (!serviceState.enabled) return;
            if (key === 'ip_telephony' || key === 'online_store') return;

            const service = this.config.services[key];
            if (!service || service.priceFromTariff) return;

            const isIncluded = selectedTariff?.includedServices?.includes(key);
            const basePrice  = service.prices[this.state.currency];
            const channels   = service.hasChannels ? serviceState.channels : 1;
            let totalPrice   = 0;

            if (isIncluded && service.hasChannels) {
                const additionalChannels = channels - 1;
                if (additionalChannels > 0) totalPrice = basePrice * additionalChannels;
                else return;
            } else if (isIncluded) {
                return;
            } else {
                totalPrice = basePrice * channels;
            }

            if (service.type !== 'one_time') monthly += totalPrice;
        });

        if (this.state.selectedServices['online_store']?.enabled) {
            const service = this.config.services['online_store'];
            if (service) monthly += service.prices[this.state.currency];
        }

        const period = monthly * this.state.periodMonths;

        return { monthly, period, oneTime: 0, grand: period };
    }

    preparePDFData() {
        if (!this.state.selectedTariff) return null;

        const tariff     = this.config.tariffs[this.state.selectedTariff];
        const tariffPrice = this.getTariffPrice(tariff);
        const totals     = this.calculateTotals();
        const contacts   = this.getContactsForApiUrl();

        const now  = new Date();
        const date = `${String(now.getDate()).padStart(2,'0')}.${String(now.getMonth()+1).padStart(2,'0')}.${now.getFullYear()}`;

        return {
            client_name:   this.state.clientName,
            client_email:  this.state.clientEmail || null,
            manager_name:  this.state.managerName,
            date,
            tariff: {
                key:           this.state.selectedTariff,
                name:          tariff.name,
                period_months: this.state.periodMonths,
                monthly_price: tariffPrice
            },
            tariff_features:   [],
            additional_users:  this.state.extraUsers > 0 ? {
                quantity:       this.state.extraUsers,
                price_per_user: tariff.extraUserPrice[this.state.currency]
            } : null,
            modules:           [],
            one_time_services: [],
            contacts: {
                phone:    contacts.phone,
                website:  contacts.website,
                telegram: contacts.telegram
            },
            currency:      this.getCurrencyDisplayName(),
            validity_days: 14
        };
    }

    getProposalPDFStyles() {
        return `
            * { margin: 0; padding: 0; box-sizing: border-box; }
            :root {
                --primary-blue: #2B4BFF; --dark-blue: #1a237e; --light-bg: #f7f6f5;
                --text-dark: #1a1a2e; --text-gray: #6b7280; --border-color: #e5e7eb;
                --icon-bg: rgba(69, 112, 255, 0.5);
            }
            body { font-family: Arial, sans-serif; background: #f7f6f5; color: var(--text-dark); line-height: 1.6; }
            .page { width: 794px; min-height: 1123px; margin: 0 auto; background: var(--light-bg); position: relative; overflow: hidden; page-break-after: always; }
            .cover-page { display: flex; flex-direction: column; align-items: center; padding: 80px 60px 0; }
            .logo-container { display: flex; flex-direction: column; align-items: center; margin-bottom: 60px; }
            .logo-icon { width: 160px; height: 160px; margin-bottom: 20px; }
            .logo-icon svg { width: 100%; height: 100%; }
            .logo-text { display: flex; align-items: center; font-size: 32px; font-weight: 700; letter-spacing: 2px; }
            .logo-text span:first-child { color: var(--text-dark); }
            .logo-text .crm-badge { background: var(--primary-blue); color: white; padding: 4px 12px; border-radius: 6px; margin-left: 4px; }
            .main-title { font-size: 52px; font-weight: 900; text-align: center; line-height: 1.2; margin-bottom: 80px; color: var(--text-dark); }
            .info-section { width: 100%; max-width: 400px; }
            .info-row { display: flex; align-items: center; margin-bottom: 40px; }
            .info-icon { width: 56px; height: 56px; background: var(--icon-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; flex-shrink: 0; }
            .info-icon svg { width: 28px; height: 28px; color: var(--primary-blue); }
            .info-label { font-size: 18px; font-weight: 500; color: var(--text-dark); }
            .cover-decoration { position: absolute; bottom: 0; right: 0; width: 100%; height: 150px; overflow: hidden; }
            .wave-shape { position: absolute; bottom: 0; right: 0; width: 350px; height: 150px; }
            .wave-dark { fill: var(--dark-blue); } .wave-light { fill: var(--primary-blue); }
            .tariff-page, .cost-page { padding: 40px 50px; position: relative; }
            .header-decoration { position: absolute; top: 0; right: 0; width: 300px; height: 80px; background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%); border-bottom-left-radius: 80px; }
            .page-header { display: flex; align-items: center; margin-bottom: 50px; }
            .tariff-header { display: flex; justify-content: flex-start; gap: 200px; margin-bottom: 30px; }
            .tariff-title { font-size: 22px; font-weight: 700; color: var(--text-dark); }
            .tariff-table, .cost-table { width: 100%; border: 2px solid var(--primary-blue); border-radius: 12px; overflow: hidden; margin-bottom: 40px; }
            .table-header { display: flex; background: white; border-bottom: 1px solid var(--border-color); }
            .table-header-cell { padding: 20px 24px; font-weight: 700; color: var(--primary-blue); font-size: 16px; }
            .table-header-cell:first-child { flex: 1; }
            .table-header-cell:last-child { width: 80px; display: flex; align-items: center; justify-content: center; }
            .checkmark { width: 24px; height: 24px; color: var(--text-dark); }
            .table-row { display: flex; background: white; border-bottom: 1px solid var(--border-color); }
            .table-row:last-child { border-bottom: none; }
            .table-cell { padding: 20px 24px; font-size: 16px; color: var(--text-dark); }
            .table-cell:first-child { flex: 1; }
            .table-cell:last-child { width: 80px; display: flex; align-items: center; justify-content: center; }
            .feature-title { font-weight: 500; }
            .feature-subtitle { color: var(--text-gray); font-size: 14px; margin-top: 2px; }
            .summary-section { margin-top: 40px; }
            .summary-row { font-size: 18px; font-weight: 700; color: var(--text-dark); margin-bottom: 16px; }
            .section-subtitle { font-size: 20px; font-weight: 700; color: var(--text-dark); }
            .bottom-decoration { position: absolute; bottom: 0; left: 0; width: 100%; height: 100px; }
            .bottom-wave { position: absolute; bottom: 0; left: 0; width: 250px; height: 100px; }
            .cost-table .table-header-cell:last-child { width: 150px; text-align: right; }
            .cost-table .table-cell:last-child { width: 150px; text-align: right; }
            .cost-table .table-row.total-row .table-cell { font-weight: 700; color: var(--primary-blue); }
            .section-title { font-size: 24px; font-weight: 700; text-align: center; color: var(--text-dark); margin-bottom: 40px; margin-top: 30px; }
            .tagline { text-align: center; margin-bottom: 40px; }
            .tagline-text { font-size: 20px; font-weight: 700; color: var(--text-dark); line-height: 1.5; }
            .tagline-text span { color: var(--primary-blue); }
            .contacts-section { display: flex; justify-content: center; gap: 60px; margin-bottom: 50px; }
            .contact-item { display: flex; flex-direction: column; align-items: center; text-align: center; }
            .contact-icon { width: 56px; height: 56px; background: var(--icon-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
            .contact-icon svg { width: 26px; height: 26px; color: var(--primary-blue); }
            .contact-value { font-size: 14px; color: var(--text-dark); font-weight: 500; }
            .validity-notice { text-align: center; font-size: 18px; font-weight: 600; color: var(--text-dark); }
            .bottom-decoration-left { position: absolute; bottom: 0; left: 0; width: 250px; height: 120px; }
            .bottom-decoration-right { position: absolute; bottom: 0; right: 0; width: 250px; height: 120px; }
        `;
    }

    async generatePDFBlob() {
        const htmlContent = this.generatePDFContent();
        const element     = document.createElement('div');
        element.innerHTML = htmlContent;
        element.style.cssText = 'background: #f7f6f5; font-family: Arial, sans-serif; color: #1a1a2e;';
        document.body.appendChild(element);

        const opt = {
            margin: [0, 0, 0, 0],
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false, backgroundColor: '#f7f6f5' },
            jsPDF: { unit: 'px', format: [794, 1123], orientation: 'portrait', compress: true },
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

    generateDescription() {
        const totals = this.calculateTotals();
        const parts  = [];

        if (this.state.selectedTariff) {
            const tariff         = this.config.tariffs[this.state.selectedTariff];
            const selectedTariff = this.config.tariffs[this.state.selectedTariff];
            let hasAdditionalServices = false;

            Object.entries(this.state.selectedServices).forEach(([key, serviceState]) => {
                if (!serviceState.enabled) return;
                const service = this.config.services[key];
                if (!service || service.priceFromTariff) return;
                if (key === 'ip_telephony' || key === 'online_store') return;
                const isIncluded = selectedTariff?.includedServices?.includes(key);
                if (!isIncluded) hasAdditionalServices = true;
                else if (service.hasChannels && serviceState.channels > 1) hasAdditionalServices = true;
            });

            if (this.state.extraUsers > 0) hasAdditionalServices = true;

            let periodText = '6 мес';
            if (this.state.periodMonths === 12) periodText = '12 мес';
            else if (this.state.periodMonths === 8) periodText = '8 мес';

            let description = `Тариф "${tariff.name}"`;
            if (hasAdditionalServices) description += ` + Доп пакеты`;
            description += ` × ${periodText}`;
            parts.push(description);
        }

        parts.push(`ИТОГО: ${this.formatTotalPrice(totals.grand)}`);
        return parts.join('. ');
    }

    async saveAndDownloadPDF() {
        if (!this.state.selectedTariff) {
            alert('Пожалуйста, выберите тариф');
            return;
        }

        this.showLoading();

        try {
            await this.sendToServerWithBlob();
        } catch (error) {
            console.error('Save error:', error);
            alert('Ошибка сохранения: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }

    async sendToServerWithBlob() {
        const proposalPayload = this.preparePDFData();
        if (!proposalPayload) throw new Error('Не удалось сформировать КП');

        const totals      = this.calculateTotals();
        const currentDate = new Date().toISOString().split('T')[0];

        const payload = {
            client_name:         this.state.clientName  || '',
            client_email:        this.state.clientEmail || '',
            manager_name:        this.state.managerName || '',
            currency:            this.state.currency,
            period_months:       this.state.periodMonths,
            description:         this.generateDescription(),
            totals:              JSON.stringify(totals),
            proposal_payload:    JSON.stringify(proposalPayload),
            selected_services:   JSON.stringify(this.getSelectedServicesForPDF()),
            additional_packages: JSON.stringify(this.getAdditionalPackages()),
            saved_at:            currentDate,
        };

        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (this.state.csrfToken) headers['X-CSRF-TOKEN'] = this.state.csrfToken;

        const response = await fetch(this.state.saveUrl || '/application/kp/store', {
            method: 'POST',
            headers,
            body: JSON.stringify(payload)
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Server error: ${response.status} - ${errorText}`);
        }

        await response.json().catch(() => ({ success: true }));
        this.showSuccessModal();
    }

    showLoading()  { document.getElementById('loadingOverlay').classList.add('active'); }
    hideLoading()  { document.getElementById('loadingOverlay').classList.remove('active'); }
    showSuccessModal() { document.getElementById('successModal').classList.add('active'); }
    closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.cpGenerator = new CPGenerator();
});
