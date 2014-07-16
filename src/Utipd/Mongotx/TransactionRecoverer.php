<?php

namespace Utipd\Mongotx;

use Exception;
use UTApp\Debug\Debug;
use Utipd\Mongotx\Transactor;

/*
* TransactionRecoverer
*/
class TransactionRecoverer
{

    protected $tx_collection;
    protected $transactor;
    protected $timed_out_length = 5; // 5 seconds

    public $_debug_now = null; // for testing

    ////////////////////////////////////////////////////////////////////////

    public function __construct(\MongoCollection $tx_collection, Transactor $transactor) {
        $this->tx_collection = $tx_collection;
        $this->transactor = $transactor;
    }


    public function recover() {
        $this->recoverPendingTransactions();
        $this->recoverCommittedTransactions();
    } 

    public function recoverPendingTransactions() {
        // To recover, applications should get a list of transactions in the pending state and resume from the third step (i.e. applying the transaction to both accounts.)
        $results = $this->tx_collection->find(['state' => 'pending', 'stateEnteredDate' => ['$lte' => $this->getDate(0-$this->timed_out_length)]]);

        foreach($results as $transaction_doc) {
            // increase attempts
            $this->tx_collection->update(
                ['_id' => new \MongoId($transaction_doc['_id'])],
                ['$inc' => ['attempts' => 1]]
            );

            // execute and cleanup
            $this->transactor->executeTransaction($transaction_doc);
            $this->transactor->cleanupTransaction($transaction_doc);
        }


    }


    public function recoverCommittedTransactions() {
        // To recover, application should get a list of transactions in the committed state and resume from the fifth step (i.e. remove the pending transaction.)
        $results = $this->tx_collection->find(['state' => 'committed', 'stateEnteredDate' => ['$lte' => $this->getDate(0-$this->timed_out_length)]]);

        foreach($results as $transaction_doc) {
            // increase attempts
            $this->tx_collection->update(
                ['_id' => new \MongoId($transaction_doc['_id'])],
                ['$inc' => ['attempts' => 1]]
            );

            // just cleanup
            $this->transactor->cleanupTransaction($transaction_doc);
        }


    }


    ////////////////////////////////////////////////////////////////////////

    protected function getDate($offset=0) {
        $time = time() + $offset;
        if ($this->_debug_now !== null) {
            $time = $this->_debug_now + $offset;
        }
        return new \MongoDate($time);
    }

}

