<?php

use Utipd\Mongotx\Test\Util;
use Utipd\Mongotx\Transaction;
use Utipd\Mongotx\Transactor;
use \PHPUnit_Framework_Assert as PHPUnit;
use \PHPUnit_Framework_TestCase;

/*
* 
*/
class AccountTransferTest extends PHPUnit_Framework_TestCase
{


    public function testAccountTransferSteps() {
        Util::init();
        list($account1, $account2) = Util::setupAccounts();

        $transaction = new Transaction();
        $transaction
            ->setSourceID($account1['_id'])
            ->setDestID($account2['_id'])
            ->setSourceUpdateQuery(['$inc' => ['balance' => -200]])
            ->setDestUpdateQuery(['$inc' => ['balance' => 200]])
            ;

        $transactor = Util::newTransactor();
        // $transactor->execute($transaction);
        
        // create
        $transaction_doc = $transactor->createTransactionDocument($transaction);
        PHPUnit::assertEquals('initial', $transaction_doc['state']);
        $transaction_doc = Util::getMongoDB()->transactions->findOne(['_id' => $transaction_doc['_id']]);
        PHPUnit::assertEquals('initial', $transaction_doc['state']);

        // switch to pending
        $entered_pending = $transactor->enterPendingStage($transaction_doc['_id']);
        if (!$entered_pending) { throw new Exception("Execution failed. Did not enter pending stage", 1); }
        $transaction_doc = Util::getMongoDB()->transactions->findOne(['_id' => $transaction_doc['_id']]);
        PHPUnit::assertEquals('pending', $transaction_doc['state']);

        // apply transaction id to collections
        $transactor->applyPendingTransactions($transaction_doc);
        $account1 = Util::getMongoDB()->accounts->findOne(['_id' => $account1['_id']]);
        PHPUnit::assertEquals(1, count($account1['pendingTransactions']));
        PHPUnit::assertEquals(new \MongoId($transaction_doc['_id']), new \MongoId($account1['pendingTransactions'][0]));
        $account2 = Util::getMongoDB()->accounts->findOne(['_id' => $account2['_id']]);
        PHPUnit::assertEquals(1, count($account2['pendingTransactions']));
        PHPUnit::assertEquals(new \MongoId($transaction_doc['_id']), new \MongoId($account2['pendingTransactions'][0]));

        // switch to committed
        $entered_committed = $transactor->enterCommittedStage($transaction_doc['_id']);
        if (!$entered_committed) { throw new Exception("Execution failed. Did not enter pending stage", 1); }
        $transaction_doc = Util::getMongoDB()->transactions->findOne(['_id' => $transaction_doc['_id']]);
        PHPUnit::assertEquals('committed', $transaction_doc['state']);

        // remove the pending transactions id
        $transactor->removePendingTransactions($transaction_doc);
        $account1 = Util::getMongoDB()->accounts->findOne(['_id' => $account1['_id']]);
        PHPUnit::assertEquals(0, count($account1['pendingTransactions']));
        $account2 = Util::getMongoDB()->accounts->findOne(['_id' => $account2['_id']]);
        PHPUnit::assertEquals(0, count($account2['pendingTransactions']));

        // switch to done
        $entered_done = $transactor->enterDoneStage($transaction_doc['_id']);
        if (!$entered_done) { throw new Exception("Execution failed. Did not enter done stage", 1); }
        $transaction_doc = Util::getMongoDB()->transactions->findOne(['_id' => $transaction_doc['_id']]);
        PHPUnit::assertEquals('done', $transaction_doc['state']);


        // check balances
        $account1 = Util::getMongoDB()->accounts->findOne(['_id' => $account1['_id']]);
        PHPUnit::assertEquals(800, $account1['balance']);
        $account2 = Util::getMongoDB()->accounts->findOne(['_id' => $account2['_id']]);
        PHPUnit::assertEquals(1200, $account2['balance']);

    }

    public function testSimpleAccountTransfer() {
        Util::init();
        list($account1, $account2) = Util::setupAccounts();

        $transaction = new Transaction();
        $transaction
            ->setSourceID($account1['_id'])
            ->setDestID($account2['_id'])
            ->setSourceUpdateQuery(['$inc' => ['balance' => -200]])
            ->setDestUpdateQuery(['$inc' => ['balance' => 200]])
            ;

        $transactor = Util::newTransactor();
        $transactor->execute($transaction);
        

        // check balances
        $account1 = Util::getMongoDB()->accounts->findOne(['_id' => $account1['_id']]);
        PHPUnit::assertEquals(800, $account1['balance']);
        $account2 = Util::getMongoDB()->accounts->findOne(['_id' => $account2['_id']]);
        PHPUnit::assertEquals(1200, $account2['balance']);

    }




}
