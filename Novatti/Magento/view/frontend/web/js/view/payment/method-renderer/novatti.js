define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'ko',
        'uiRegistry',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'mage/url',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/shipping-save-processor',
        'Magento_Customer/js/customer-data',
        'Magento_Ui/js/modal/modal',
        'mage/storage',
        'mage/translate',
        'HPF'
    ],
    function (
        Component,
        quote,
        $,
        ko,
        uiRegistry,
        additionalValidators,
        setPaymentInformationAction,
        url,
        customer,
        placeOrderAction,
        fullScreenLoader,
        messageList,
        shippingSaveProcessor,
        customerData,
        modal,
        storage,
        $t,
        HPF
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Novatti_Magento/payment/novatti-form',
                novatti_pop: '',
                txn_id: '',
                cc_owner: '',
                cc_type: '',
                cc_last_4: '',
                cc_exp_month: '',
                cc_exp_year: '',
                cc_trans_id: ''
            },

            initialize: function () {
                this._super();
                return this;
            },

            preparePayment: function () {
                var hpfOptions  = {
                    merchantID: window.checkoutConfig.payment.novatti.merchant_id,
                    token: window.checkoutConfig.payment.novatti.token,
                    style: {
                        "input": "font-size: 14px; color: #2D353B; background: #F8F8F8;"
                    }
                }
                var self = this;
                var options = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    clickableOverlay: false,
                    modalClass: 'novatti-payment-popup',
                    overlayClass: 'novatti-overlay',
                    title: '',
                    buttons: [],
                    closed: function () {
                        HPF.destroy();
                    }
                };

                this.popup = modal(options, $('.novatti-wrapper'));
                $('.novatti-wrapper').modal('openModal');
                this.initHPF(hpfOptions);
            },

            initHPF: function (hpfOptions) {
                HPF.init(hpfOptions);
            },

            closePopup: function () {
                this.popup.closeModal();
            },

            submitCard: function () {
                fullScreenLoader.startLoader();
                var txnId = 'Txn' + Date.now();
                this.txn_id = txnId;
                HPF.generateToken(this.callback, txnId);
            },

            callback: function (data) {
                var self = uiRegistry.get("checkout.steps.billing-step.payment.payments-list.novatti");
                let respCode = data.ResponseCode;

                if (respCode === '1000') {
                    self.performPayment(data.Token, self.txn_id);
                } else {
                    $(".action-close").trigger( "click" );
                    fullScreenLoader.stopLoader();
                    self.messageContainer.addErrorMessage({message: $t('Tokenization failed. Please contact merchant.')});               
                }
            },

            performPayment: function (token, txnId) {
                storage.post(
                    'novatti/process/transaction',
                    JSON.stringify({secure_token: token, txn_id: txnId, token: window.checkoutConfig.payment.novatti.token, guest_email: quote.guestEmail}),
                    true
                ).done(function (data) {
                    data = $.parseJSON(data);
                    var self = uiRegistry.get("checkout.steps.billing-step.payment.payments-list.novatti");
                    if (data.Result.ResponseCode === '1000') {
                        self.cc_owner = data.PaymentInfo.CardHolder;
                        self.cc_type = data.PaymentInfo.PaymentBrand;
                        self.cc_last_4 = data.PaymentInfo.Last4;
                        self.cc_exp_month = data.PaymentInfo.CardExpiryMonth;
                        self.cc_exp_year = data.PaymentInfo.CardExpiryYear;
                        self.cc_trans_id = data.Result.PaymentID;
                        self.placeOrder();
                    } else {
                        self.messageContainer.addErrorMessage({message: data.Result.ResponseMessage});
                        $(".action-close").trigger( "click" );
                    }
                    fullScreenLoader.stopLoader();
                });
            },

            /**
             * Get data
             * @returns {Object}
             */
            getData: function() {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'cc_owner': this.cc_owner,
                        'cc_type': this.cc_type,
                        'cc_last_4': this.cc_last_4,
                        'cc_exp_month': this.cc_exp_month,
                        'cc_exp_year': this.cc_exp_year,
                        'cc_trans_id': this.cc_trans_id,
                        'last_trans_id': this.txn_id
                    }
                };
            },           

            /**
             * Returns payment image path
             * @returns {String}
             */
            getNovattiLogoSrc: function () {
                return window.checkoutConfig.payment.novatti.paymentLogoSrc;
            },

            /**
             * Returns payment description
             * @returns {String}
             */
            getNovattiDescription: function () {
                return window.checkoutConfig.payment.novatti.paymentDescription;
            }
        });
    }
);
