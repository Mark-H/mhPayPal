<form action="[[+action]]" method="[[+config.method]]" class="mhpp_form" id="mhpp_form_[[+config.id]]">
    <p>Your donation will be safely processed by PayPal, allowing you to donate via a PayPal account or directly with a credit card.</p>
    [[+errors:notempty=`
        <p class="error">Uh oh.. The following error(s) were found in your form: <br />[[+errors]]</p>
    `]]
    <div class="formfield mhpp_amount_wrapper">
        <label for="mhpp_amount_[[+config.id]]">Amount</label>
        <div class="field">
            <select name="currency" class="mhpp_currency" id="mhpp_currency_[[+config.id]]">
                <option value="EUR"[[+currency_EUR:notempty=` selected="selected"`]]>EUR &euro;</option>
                <option value="USD"[[+currency_USD:notempty=` selected="selected"`]]>USD &#36;</option>
                <option value="GBP"[[+currency_GBP:notempty=` selected="selected"`]]>GBP &#163;</option>
            </select>


            <input type="text" name="amount" class="mhpp_amount" id="mhpp_amount_[[+config.id]]" />
            [[+currency.error]] [[+amount.error]]
        </div>
    </div>

    <div class="formfield mhpp_submit_wrapper">
        <div class="field">
            <input type="submit" name="[[+config.submitVar]]" value="Donate!" />
        </div>
    </div>
</form>
