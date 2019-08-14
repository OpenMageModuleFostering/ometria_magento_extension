document.observe("dom:loaded", function () {
    // We want to know when the checkout step changes
    // Overwrite the original gotoSection function in opcheckout.js and update our variable
    if (typeof Checkout != 'undefined'){
        var originalCheckout = Checkout.prototype.gotoSection
        Checkout.prototype.gotoSection = function () {
            try{
                if (window.ometria) ometria.trackCheckout(arguments[0]);
            } catch(e){}
            originalCheckout.apply(this, arguments);
        };
    }
});