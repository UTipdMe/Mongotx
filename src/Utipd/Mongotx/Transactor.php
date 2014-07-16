<?php

namespace Utipd\Mongotx;

use Exception;
use Utipd\Mongotx\Transaction;

/*
* Transactor
*/
class Transactor
{

    protected $tx_collection = null;
    protected $items_collection = null;

    public $_debug_now = null; // for testing
    public $_debug_fail_step = null; // for testing

    ////////////////////////////////////////////////////////////////////////

    public function __construct(\MongoCollection $tx_collection, \MongoCollection $items_collection) {
        $this->tx_collection = $tx_collection;
        $this->items_collection = $items_collection;
    }

    public function execute(Transaction $tx) {
        $transaction_doc = $this->setup($tx);
        
        $this->executeTransaction($transaction_doc);

        $this->cleanupTransaction($transaction_doc);
    }



    public function setup(Transaction $tx) {
        // create new transaction
        $transaction_doc = $this->createTransactionDocument($tx);

        // switch to pending
        $entered_pending = $this->enterPendingStage($transaction_doc['_id']);
        if (!$entered_pending) { throw new Exception("Execution failed. Did not enter pending stage", 1); }

        return $transaction_doc;
    }

    public function executeTransaction($transaction_doc) {
        // apply transaction id to collections
        $this->applyPendingTransactions($transaction_doc);

        // switch to committed
        $entered_committed = $this->enterCommittedStage($transaction_doc['_id']);
        if (!$entered_committed) { throw new Exception("Execution failed. Did not enter committed stage", 1); }
    }

    public function cleanupTransaction($transaction_doc) {
        // remove the pending transactions id
        $this->removePendingTransactions($transaction_doc);

        // switch to done
        $entered_done = $this->enterDoneStage($transaction_doc['_id']);
        if (!$entered_done) { throw new Exception("Execution failed. Did not enter done stage", 1); }
    }



    public function createTransactionDocument(Transaction $tx) {
        $tx_doc = [
            'source_id'          => $tx->getSourceID(),
            'dest_id'            => $tx->getDestID(),
            'source_update'      => json_encode($tx->getSourceUpdateQuery()),
            'dest_update'        => json_encode($tx->getDestUpdateQuery()),
            // 'source_constraints' => $tx->getSourceConstraints() ? json_encode($tx->getSourceConstraints()) : null,
            // 'dest_constraints'   => $tx->getDestConstraints() ? json_encode($tx->getDestConstraints()) : null,

            'state'              => 'initial',
            'stateEnteredDate'   => $this->getDate(),
            'attempts'           => 1,
        ];
        $status = $this->tx_collection->save($tx_doc);
        return $tx_doc;
    }

    public function enterPendingStage($tx_id) {
        $result = $this->tx_collection->update(
            ['_id' => new \MongoId($tx_id), 'state' => 'initial'],
            ['$set' => ['state' => 'pending', 'stateEnteredDate' => $this->getDate()]]
        );
        return ($result['n'] == 1);
    }

    public function applyPendingTransactions($transaction_doc) {
        // print_r(json_decode($transaction_doc['source_update'], true));

        $tx_id = new \MongoId($transaction_doc['_id']);
        $result = $this->items_collection->update(
            ['_id' => $transaction_doc['source_id'], 'pendingTransactions' => ['$ne' => $tx_id]],
            array_merge(json_decode($transaction_doc['source_update'], true), ['$push' => ['pendingTransactions' => $tx_id]])
        );
        if (isset($this->_debug_fail_step) AND $this->_debug_fail_step == 'apply_pending') { return; }
        $result = $this->items_collection->update(
            ['_id' => $transaction_doc['dest_id'], 'pendingTransactions' => ['$ne' => $tx_id]],
            array_merge(json_decode($transaction_doc['dest_update'], true), ['$push' => ['pendingTransactions' => $tx_id]])
        );
    }

    public function enterCommittedStage($tx_id) {
        $result = $this->tx_collection->update(
            ['_id' => new \MongoId($tx_id), 'state' => 'pending'],
            ['$set' => ['state' => 'committed', 'stateEnteredDate' => $this->getDate()]]
        );
        return ($result['n'] == 1);
    }

    public function removePendingTransactions($transaction_doc) {
        $tx_id = new \MongoId($transaction_doc['_id']);
        $result = $this->items_collection->update(
            ['_id' => $transaction_doc['source_id']],
            ['$pull' => ['pendingTransactions' => $tx_id]]
        );
        if (isset($this->_debug_fail_step) AND $this->_debug_fail_step == 'remove_pending') { return; }
        $result = $this->items_collection->update(
            ['_id' => $transaction_doc['dest_id']],
            ['$pull' => ['pendingTransactions' => $tx_id]]
        );
    }


    public function enterDoneStage($tx_id) {
        $result = $this->tx_collection->update(
            ['_id' => new \MongoId($tx_id), 'state' => 'committed'],
            ['$set' => ['state' => 'done', 'stateEnteredDate' => $this->getDate()]]
        );
        return ($result['n'] == 1);
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

