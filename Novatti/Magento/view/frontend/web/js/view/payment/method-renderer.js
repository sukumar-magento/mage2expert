define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'novatti',
                component: 'Novatti_Magento/js/view/payment/method-renderer/novatti'
            }
        );
        return Component.extend({});
    }
);