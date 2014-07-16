<?php

use Utipd\Mongotx\Test\Util;
use Utipd\Mongotx\Transaction;
use Utipd\Mongotx\Transactor;
use \PHPUnit_Framework_Assert as PHPUnit;
use \PHPUnit_Framework_TestCase;

/*
* 
*/
class TransferRecoveryTest extends PHPUnit_Framework_TestCase
{


    public function testTransferRecoveries() {
        for ($fail_step=Util::FAIL_STEP_AFTER_ENTER_PENDING; $fail_step <= Util::FAIL_STEP_AFTER_REMOVE_PENDING; $fail_step++) { 
            Util::init();

            list($account1, $account2) = Util::setupAccounts();

            $transaction = new Transaction();
            $transaction
                ->setSourceID($account1['_id'])
                ->setDestID($account2['_id'])
                ->setSourceUpdateQuery(['$inc' => ['balance' => -200]])
                ->setDestUpdateQuery(['$inc' => ['balance' => 200]])
                ;

            // fail
            $transaction_doc = Util::doTransferAndFail($transaction, $fail_step);

            // recover
            Util::recover();

            // check balances
            $account1 = Util::getMongoDB()->accounts->findOne(['_id' => $account1['_id']]);
            PHPUnit::assertEquals(800, $account1['balance']);
            $account2 = Util::getMongoDB()->accounts->findOne(['_id' => $account2['_id']]);
            PHPUnit::assertEquals(1200, $account2['balance']);

            // check attempts
            $transaction_doc = Util::reloadTransactionDoc($transaction_doc);
            PHPUnit::assertEquals(2, $transaction_doc['attempts']);
        }



    }




}
