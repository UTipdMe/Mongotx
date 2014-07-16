<?php

namespace Utipd\Mongotx\Test;

use Exception;
use MongoCollection;
use UTApp\Debug\Debug;
use Utipd\Mongotx\TransactionRecoverer;
use Utipd\Mongotx\Transactor;

/*
* Util
*/
class Util
{

    public static function init() {
        $mongo_db = self::getMongoDB();
        $mongo_db->accounts->remove([]);
        $mongo_db->transactions->remove([]);
    }


    public static function getMongoDB() {
        $connection_string = 'mongodb://localhost:27017';
        $client = new \MongoClient($connection_string);
        return $client->selectDB('utipd-test');
    }

    public static function newTransactor() {
        return new Transactor(self::getMongoDB()->transactions, self::getMongoDB()->accounts);
    }

    public static function newTransactionRecoverer() {
        return new TransactionRecoverer(self::getMongoDB()->transactions, self::newTransactor());
    }

    public static function doTransfer() {
        list($account1, $account2) = self::setupAccounts();

        $tx = new Transaction();
        $tx
            ->setSourceID($account1['_id'])
            ->setDestID($account2['_id'])
            ->setSourceUpdateQuery(['$inc' => ['balance' => -200]])
            ->setDestUpdateQuery(['$inc' => ['balance' => 200]])
            ;

        $transactor = self::newTransactor();
        $transactor->execute($tx);

    }

    const FAIL_STEP_AFTER_ENTER_PENDING       = 2;
    const FAIL_STEP_AFTER_APPLY_PENDING_HALF  = 3;
    const FAIL_STEP_AFTER_APPLY_PENDING       = 4;
    const FAIL_STEP_AFTER_ENTER_COMMITTED     = 5;
    const FAIL_STEP_AFTER_REMOVE_PENDING_HALF = 6;
    const FAIL_STEP_AFTER_REMOVE_PENDING      = 7;

    public static function doTransferAndFail($transaction, $fail_step=null, $time_offset=-6) {
        $transactor = self::newTransactor();
        $transactor->_debug_now = time() + $time_offset;

        // create
        $transaction_doc = $transactor->createTransactionDocument($transaction);

        // switch to pending
        $entered_pending = $transactor->enterPendingStage($transaction_doc['_id']);
        if (!$entered_pending) { throw new Exception("Execution failed. Did not enter pending stage", 1); }
        if ($fail_step == self::FAIL_STEP_AFTER_ENTER_PENDING) { return $transaction_doc; }

        // apply transaction id to collections
        if ($fail_step == self::FAIL_STEP_AFTER_APPLY_PENDING_HALF) {
            $transactor->_debug_fail_step = 'apply_pending';
        }
        $transactor->applyPendingTransactions($transaction_doc);
        if ($fail_step == self::FAIL_STEP_AFTER_APPLY_PENDING OR $fail_step == self::FAIL_STEP_AFTER_APPLY_PENDING_HALF) { return $transaction_doc; }

        // switch to committed
        $entered_committed = $transactor->enterCommittedStage($transaction_doc['_id']);
        if (!$entered_committed) { throw new Exception("Execution failed. Did not enter pending stage", 1); }
        if ($fail_step == self::FAIL_STEP_AFTER_ENTER_COMMITTED) { return $transaction_doc; }

        // remove the pending transactions id
        if ($fail_step == self::FAIL_STEP_AFTER_REMOVE_PENDING_HALF) {
            $transactor->_debug_fail_step = 'remove_pending';
        }
        $transactor->removePendingTransactions($transaction_doc);
        if ($fail_step == self::FAIL_STEP_AFTER_REMOVE_PENDING OR $fail_step == self::FAIL_STEP_AFTER_REMOVE_PENDING_HALF) { return $transaction_doc; }

        // switch to done
        $entered_done = $transactor->enterDoneStage($transaction_doc['_id']);
        return $transaction_doc;
    }

    public static function recover() {
        $recoverer = self::newTransactionRecoverer();
        $recoverer->recover();
    }

    public static function setupAccounts() {
        $account1 = ['balance' => 1000, 'name' => 'account1'];
        self::getMongoDB()->accounts->save($account1);
        $account2 = ['balance' => 1000, 'name' => 'account2'];
        self::getMongoDB()->accounts->save($account2);

        return [$account1, $account2];
    }

    public static function reloadTransactionDoc($transaction_doc) {
        return self::getMongoDB()->transactions->findOne(['_id' => $transaction_doc['_id']]);

    }








    ////////////////////////////////////////////////////////////////////////

}

