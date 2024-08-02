<?php

namespace ReachDigital\AutoInvoice\Observer;

use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\InvoiceManagementInterface;
use Magento\Sales\Model\Order;

class AutoInvoiceAfterPlaceOrder implements ObserverInterface
{
    private InvoiceManagementInterface $invoiceManagement;
    private TransactionFactory $transactionFactory;
    private array $paymentMethods;

    /**
     * @param InvoiceManagementInterface $invoiceManagement
     * @param TransactionFactory $transactionFactory
     * @param array $paymentMethods List of payment methods for which to apply auto-invoicing. If not provided through
     *                              DI and left to be the default empty array, auto-invoicing is applied to all orders.
     */
    public function __construct(
        InvoiceManagementInterface $invoiceManagement,
        TransactionFactory $transactionFactory,
        array $paymentMethods = []
    ) {
        $this->invoiceManagement = $invoiceManagement;
        $this->transactionFactory = $transactionFactory;
        $this->paymentMethods = $paymentMethods;
    }

    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getData('order');

        // Check if we need to filter by payment method.
        if (count($this->paymentMethods)) {
            $orderPaymentMethod = $order->getPayment()->getMethod();
            if (!in_array($orderPaymentMethod, $this->paymentMethods)) {
                return;
            }
        }

        try {
            if (!$order->canInvoice()) {
                throw new \Exception('Order does not allow being invoiced.');
            }
            if (!$order->getState() == 'new') {
                throw new \Exception('Order must bew in \'new\' state.');
            }

            $invoice = $this->invoiceManagement->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();

            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));

            $transaction = $this->transactionFactory->create()
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transaction->save();

            $order->addStatusHistoryComment('Order automatically invoiced and set to processing state.', false);
            $order->save();
        } catch (\Exception $e) {
            $order->addStatusHistoryComment('Failed to auto-invoice order. Exception message: '.$e->getMessage(), false);
            $order->save();
        }
    }
}
