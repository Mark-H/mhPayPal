[[!FormIt?
  &hooks=`makeDonateHook[[+extrahooks:notempty=`,[[+extrahooks]]`]]`
  &validate=`amount:required:isNumber`
  &submitVar=`[[+id]]-submit`
  &ppReturn=`[[+return]]`
  &ppFailure=`[[+failure]]`
  &ppProject=`[[+project]]`
  [[+extrasettings]]
  ]]
<form action="[[+action]]" method="[[+method]]" id="[[+id]]">
    <p>Your donation will be safely processed by PayPal, allowing you to donate via a PayPal account or directly with a credit card.</p>
    <div class="formfield amount">
        <label for="[[+id]]-amount">Amount</label>
        <div class="field">
            <select name="amount_cur" id="[[+id]]-amount_cur">
                <option value="EUR" [[!+fi.amount_cur:FormItIsSelected=`EUR`]] >EUR &euro;</option>
                <option value="USD" [[!+fi.amount_cur:FormItIsSelected=`USD`]] >USD &#36;</option>
                <option value="GBP" [[!+fi.amount_cur:FormItIsSelected=`GBP`]] >GBP &#163;</option>
            </select>

            <input type="text" name="amount" class="donate_amount" id="[[+id]]-amount" />
            [[!+fi.error.amount_cur:notempty=`<p>[[!+fi.error.amount_cur]]</p>`]]
            [[!+fi.error.amount:notempty=`<p>[[!+fi.error.amount]]</p>`]]
        </div>
    </div>

    <div class="formfield amount">
        <div class="field">
            <input type="submit" name="[[+id]]-submit" class="donate_submit" id="[[+id]]-submit" value="Donate!" />
        </div>
    </div>
</form>
