# ReachDigital_AutoInvoice

Trivial module that auto-invoices orders and moves them onto the processing state/status.

By default this is done for all orders, but you can use DI to provide a list of specific payment method codes to do this for:

```xml
<type name="ReachDigital\AutoInvoice\Observer\AutoInvoiceAfterPlaceOrder">
    <arguments>
        <argument name="paymentMethods" xsi:type="array">
            <item name="banktransfer" xsi:type="string">banktransfer</item>
        </argument>
    </arguments>
</type>
```
