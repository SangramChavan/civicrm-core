<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 * Class CRM_Core_Payment_AuthorizeNetTest
 * @group headless
 */
class CRM_Core_Payment_AuthorizeNetTest extends CiviUnitTestCase {

  use \Civi\Test\GuzzleTestTrait;

  /**
   * @var \CRM_Core_Payment_AuthorizeNet
   */
  protected $processor;

  public function setUp() {
    parent::setUp();
    $this->_paymentProcessorID = $this->paymentProcessorAuthorizeNetCreate();

    $this->processor = Civi\Payment\System::singleton()->getById($this->_paymentProcessorID);
    $this->_financialTypeId = 1;

    // for some strange unknown reason, in batch mode this value gets set to null
    // so crude hack here to avoid an exception and hence an error
    $GLOBALS['_PEAR_ERRORSTACK_OVERRIDE_CALLBACK'] = [];
  }

  public function tearDown() {
    $this->quickCleanUpFinancialEntities();
  }

  /**
   * Test doing a one-off payment.
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function testSinglePayment() {
    $this->createMockHandler([$this->getExpectedSinglePaymentResponse()]);
    $this->setUpClientWithHistoryContainer();
    $this->processor->setGuzzleClient($this->getGuzzleClient());
    $params = $this->getBillingParams();
    $params['amount'] = 5.24;
    $this->processor->doPayment($params);
    $this->assertEquals($this->getExpectedSinglePaymentRequest(), $this->getRequestBodies()[0]);
  }

  /**
   * Get the expected response from Authorize.net.
   *
   * @return string
   */
  public function getExpectedSinglePaymentResponse() {
    return '"1","1","1","(TESTMODE) This transaction has been approved.","000000","P","0","","","5.24","CC","auth_capture","","John","O&#39;Connor","","","","","","","","","","","","","","","","","","","","","","","",""';
  }

  /**
   *  Get the expected request from Authorize.net.
   *
   * @return string
   */
  public function getExpectedSinglePaymentRequest() {
    return 'x_login=4y5BfuW7jm&x_tran_key=4cAmW927n8uLf5J8&x_email_customer=&x_first_name=John&x_last_name=O%27Connor&x_address=&x_city=&x_state=&x_zip=&x_country=&x_customer_ip=&x_email=&x_invoice_num=&x_amount=5.24&x_currency_code=&x_description=&x_cust_id=&x_relay_response=FALSE&x_delim_data=TRUE&x_delim_char=%2C&x_encap_char=%22&x_card_num=4444333322221111&x_card_code=123&x_exp_date=10%2F2022&x_test_request=TRUE';
  }

  /**
   * Create a single post dated payment as a recurring transaction.
   *
   * Test works but not both due to some form of caching going on in the SmartySingleton
   */
  public function testCreateSingleNowDated() {
    $this->createMockHandler([$this->getExpectedResponse()]);
    $this->setUpClientWithHistoryContainer();
    $this->processor->setGuzzleClient($this->getGuzzleClient());
    $firstName = 'John';
    $lastName = "O\'Connor";
    $nameParams = ['first_name' => 'John', 'last_name' => $lastName];
    $contactId = $this->individualCreate($nameParams);

    $invoiceID = 123456;
    $amount = 7;

    $recur = $this->callAPISuccess('ContributionRecur', 'create', [
      'contact_id' => $contactId,
      'amount' => $amount,
      'currency' => 'USD',
      'frequency_unit' => 'week',
      'frequency_interval' => 1,
      'installments' => 2,
      'start_date' => date('Ymd'),
      'create_date' => date('Ymd'),
      'invoice_id' => $invoiceID,
      'contribution_status_id' => 2,
      'is_test' => 1,
      'payment_processor_id' => $this->_paymentProcessorID,
    ]);

    $contribution = $this->callAPISuccess('Contribution', 'create', [
      'contact_id' => $contactId,
      'financial_type_id' => 'Donation',
      'receive_date' => date('Ymd'),
      'total_amount' => $amount,
      'invoice_id' => $invoiceID,
      'currency' => 'USD',
      'contribution_recur_id' => $recur['id'],
      'is_test' => 1,
      'contribution_status_id' => 2,
    ]);

    $billingParams = $this->getBillingParams();

    $params = array_merge($billingParams, [
      'qfKey' => '08ed21c7ca00a1f7d32fff2488596ef7_4454',
      'hidden_CreditCard' => 1,
      'is_recur' => 1,
      'frequency_interval' => 1,
      'frequency_unit' => 'month',
      'installments' => 12,
      'financial_type_id' => $this->_financialTypeId,
      'is_email_receipt' => 1,
      'from_email_address' => 'john.smith@example.com',
      'receive_date' => date('Ymd'),
      'receipt_date_time' => '',
      'payment_processor_id' => $this->_paymentProcessorID,
      'price_set_id' => '',
      'total_amount' => $amount,
      'currency' => 'USD',
      'source' => 'Mordor',
      'soft_credit_to' => '',
      'soft_contact_id' => '',
      'billing_state_province-5' => 'IL',
      'state_province-5' => 'IL',
      'billing_country-5' => 'US',
      'country-5' => 'US',
      'year' => 2025,
      'month' => 9,
      'ip_address' => '127.0.0.1',
      'amount' => 7,
      'amount_level' => 0,
      'currencyID' => 'USD',
      'pcp_display_in_roll' => '',
      'pcp_roll_nickname' => '',
      'pcp_personal_note' => '',
      'non_deductible_amount' => '',
      'fee_amount' => '',
      'net_amount' => '',
      'invoiceID' => $invoiceID,
      'contribution_page_id' => '',
      'thankyou_date' => NULL,
      'honor_contact_id' => NULL,
      'first_name' => $firstName,
      'middle_name' => '',
      'last_name' => $lastName,
      'street_address' => '8 Hobbiton Road',
      'city' => 'The Shire',
      'state_province' => 'IL',
      'postal_code' => 5010,
      'country' => 'US',
      'contributionType_name' => 'My precious',
      'contributionType_accounting_code' => '',
      'contributionPageID' => '',
      'email' => 'john.smith@example.com',
      'contactID' => $contactId,
      'contributionID' => $contribution['id'],
      'contributionTypeID' => $this->_financialTypeId,
      'contributionRecurID' => $recur['id'],
    ]);

    // turn verifySSL off
    Civi::settings()->set('verifySSL', '0');
    $this->processor->doPayment($params);
    // turn verifySSL on
    Civi::settings()->set('verifySSL', '0');

    // if subscription was successful, processor_id / subscription-id must not be null
    $this->assertDBNotNull('CRM_Contribute_DAO_ContributionRecur', $recur['id'], 'processor_id',
      'id', 'Failed to create subscription with Authorize.'
    );

    // cancel it or the transaction will be rejected by A.net if the test is re-run
    $subscriptionID = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionRecur', $recur['id'], 'processor_id');
    $message = '';
    $result = $this->processor->cancelSubscription($message, ['subscriptionId' => $subscriptionID]);
    $this->assertTrue($result, 'Failed to cancel subscription with Authorize.');

    $requests = $this->getRequestBodies();
    $this->assertEquals($this->getExpectedRequest($contactId, date('Y-m-d')), $requests[0]);
    $header = $this->getRequestHeaders()[0];
    $this->assertEquals(['apitest.authorize.net'], $header['Host']);
    $this->assertEquals(['text/xml; charset=UTF8'], $header['Content-Type']);

    $this->assertEquals([
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_SSL_VERIFYPEER => Civi::settings()->get('verifySSL'),
    ], $this->container[0]['options']['curl']);
  }

  /**
   * Create a single post dated payment as a recurring transaction.
   */
  public function testCreateSinglePostDated() {
    $this->createMockHandler([$this->getExpectedResponse()]);
    $this->setUpClientWithHistoryContainer();
    $this->processor->setGuzzleClient($this->getGuzzleClient());
    $start_date = date('Ymd', strtotime('+ 1 week'));

    $firstName = 'John';
    $lastName = "O'Connor";
    $nameParams = ['first_name' => $firstName, 'last_name' => $lastName];
    $contactId = $this->individualCreate($nameParams);

    $invoiceID = 123456;
    $amount = 70.23;

    $contributionRecurParams = [
      'contact_id' => $contactId,
      'amount' => $amount,
      'currency' => 'USD',
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 3,
      'start_date' => $start_date,
      'create_date' => date('Ymd'),
      'invoice_id' => $invoiceID,
      'contribution_status_id' => '',
      'is_test' => 1,
      'payment_processor_id' => $this->_paymentProcessorID,
    ];
    $recur = $this->callAPISuccess('ContributionRecur', 'create', $contributionRecurParams);

    $contributionParams = [
      'contact_id' => $contactId,
      'financial_type_id' => $this->_financialTypeId,
      'receive_date' => $start_date,
      'total_amount' => $amount,
      'invoice_id' => $invoiceID,
      'currency' => 'USD',
      'contribution_recur_id' => $recur['id'],
      'is_test' => 1,
      'contribution_status_id' => 2,
    ];

    $contribution = $this->callAPISuccess('Contribution', 'create', $contributionParams);

    $params = [
      'qfKey' => '00ed21c7ca00a1f7d555555596ef7_4454',
      'hidden_CreditCard' => 1,
      'billing_first_name' => $firstName,
      'billing_middle_name' => '',
      'billing_last_name' => $lastName,
      'billing_street_address-5' => '8 Hobbitton Road',
      'billing_city-5' => 'The Shire',
      'billing_state_province_id-5' => 1012,
      'billing_postal_code-5' => 5010,
      'billing_country_id-5' => 1228,
      'credit_card_number' => '4007000000027',
      'cvv2' => 123,
      'credit_card_exp_date' => [
        'M' => 11,
        'Y' => 2022,
      ],
      'credit_card_type' => 'Visa',
      'is_recur' => 1,
      'frequency_interval' => 1,
      'frequency_unit' => 'month',
      'installments' => 3,
      'financial_type_id' => $this->_financialTypeId,
      'is_email_receipt' => 1,
      'from_email_address' => "{$firstName}.{$lastName}@example.com",
      'receive_date' => $start_date,
      'receipt_date_time' => '',
      'payment_processor_id' => $this->_paymentProcessorID,
      'price_set_id' => '',
      'total_amount' => $amount,
      'currency' => 'USD',
      'source' => 'Mordor',
      'soft_credit_to' => '',
      'soft_contact_id' => '',
      'billing_state_province-5' => 'IL',
      'state_province-5' => 'IL',
      'billing_country-5' => 'US',
      'country-5' => 'US',
      'year' => 2022,
      'month' => 10,
      'ip_address' => '127.0.0.1',
      'amount' => 70.23,
      'amount_level' => 0,
      'currencyID' => 'USD',
      'pcp_display_in_roll' => '',
      'pcp_roll_nickname' => '',
      'pcp_personal_note' => '',
      'non_deductible_amount' => '',
      'fee_amount' => '',
      'net_amount' => '',
      'invoice_id' => '',
      'contribution_page_id' => '',
      'thankyou_date' => NULL,
      'honor_contact_id' => NULL,
      'invoiceID' => $invoiceID,
      'first_name' => $firstName,
      'middle_name' => 'bob',
      'last_name' => $lastName,
      'street_address' => '8 Hobbiton Road',
      'city' => 'The Shire',
      'state_province' => 'IL',
      'postal_code' => 5010,
      'country' => 'US',
      'contributionPageID' => '',
      'email' => 'john.smith@example.com',
      'contactID' => $contactId,
      'contributionID' => $contribution['id'],
      'contributionRecurID' => $recur['id'],
    ];

    // if cancel-subscription has been called earlier 'subscriptionType' would be set to cancel.
    // to make a successful call for another trxn, we need to set it to something else.
    $smarty = CRM_Core_Smarty::singleton();
    $smarty->assign('subscriptionType', 'create');

    $this->processor->doPayment($params);

    // if subscription was successful, processor_id / subscription-id must not be null
    $this->assertDBNotNull('CRM_Contribute_DAO_ContributionRecur', $recur['id'], 'processor_id',
      'id', 'Failed to create subscription with Authorize.'
    );

    // cancel it or the transaction will be rejected by A.net if the test is re-run
    $subscriptionID = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionRecur', $recur['id'], 'processor_id');
    $message = '';
    $result = $this->processor->cancelSubscription($message, ['subscriptionId' => $subscriptionID]);
    $this->assertTrue($result, 'Failed to cancel subscription with Authorize.');

    $response = $this->getResponseBodies();
    $this->assertEquals($this->getExpectedResponse(), $response[0], 3);
    $requests = $this->getRequestBodies();
    $this->assertEquals($this->getExpectedRequest($contactId, date('Y-m-d', strtotime($start_date)), 70.23, 3, 4007000000027, '2022-10'), $requests[0]);
  }

  /**
   * Get the content that we expect to see sent out.
   *
   * @param int $contactID
   * @param string $startDate
   *
   * @param int $amount
   * @param int $occurrences
   * @param int $cardNumber
   * @param string $cardExpiry
   *
   * @return string
   */
  public function getExpectedRequest($contactID, $startDate, $amount = 7, $occurrences = 12, $cardNumber = 4444333322221111, $cardExpiry = '2025-09') {
    return '<?xml version="1.0" encoding="utf-8"?>
<ARBCreateSubscriptionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
  <merchantAuthentication>
    <name>4y5BfuW7jm</name>
    <transactionKey>4cAmW927n8uLf5J8</transactionKey>
  </merchantAuthentication>
  <refId>123456</refId>
  <subscription>
        <paymentSchedule>
      <interval>
        <length>1</length>
        <unit>months</unit>
      </interval>
      <startDate>' . $startDate . '</startDate>
      <totalOccurrences>' . $occurrences . '</totalOccurrences>
    </paymentSchedule>
    <amount>' . $amount . '</amount>
    <payment>
      <creditCard>
        <cardNumber>' . $cardNumber . '</cardNumber>
        <expirationDate>' . $cardExpiry . '</expirationDate>
      </creditCard>
    </payment>
      <order>
     <invoiceNumber>1</invoiceNumber>
        </order>
       <customer>
      <id>' . $contactID . '</id>
      <email>john.smith@example.com</email>
    </customer>
    <billTo>
      <firstName>John</firstName>
      <lastName>O\'Connor</lastName>
      <address>8 Hobbiton Road</address>
      <city>The Shire</city>
      <state>IL</state>
      <zip>5010</zip>
      <country>US</country>
    </billTo>
  </subscription>
</ARBCreateSubscriptionRequest>
';
  }

  /**
   * Get a successful response to setting up a recurring.
   *
   * @return string
   */
  public function getExpectedResponse() {
    return '﻿<?xml version="1.0" encoding="utf-8"?><ARBCreateSubscriptionResponse xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd"><refId>8d468ca1b1dd5c2b56c7</refId><messages><resultCode>Ok</resultCode><message><code>I00001</code><text>Successful.</text></message></messages><subscriptionId>6632052</subscriptionId><profile><customerProfileId>1512023280</customerProfileId><customerPaymentProfileId>1512027350</customerPaymentProfileId></profile></ARBCreateSubscriptionResponse>';
  }

  /**
   * Get some basic billing parameters.
   *
   * @return array
   */
  protected function getBillingParams(): array {
    return [
      'billing_first_name' => 'John',
      'billing_middle_name' => '',
      'billing_last_name' => "O'Connor",
      'billing_street_address-5' => '8 Hobbitton Road',
      'billing_city-5' => 'The Shire',
      'billing_state_province_id-5' => 1012,
      'billing_postal_code-5' => 5010,
      'billing_country_id-5' => 1228,
      'credit_card_number' => '4444333322221111',
      'cvv2' => 123,
      'credit_card_exp_date' => [
        'M' => 9,
        'Y' => 2025,
      ],
      'credit_card_type' => 'Visa',
      'year' => 2022,
      'month' => 10,
    ];
  }

}
