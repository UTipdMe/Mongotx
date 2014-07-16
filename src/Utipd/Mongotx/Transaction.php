<?php

namespace Utipd\Mongotx;

use Exception;
use MongoCollection;
use UTApp\Debug\Debug;

/*
* Transaction
*/
class Transaction
{

    protected $source_id;
    protected $dest_id;
    protected $source_update_query;
    protected $dest_update_query;
    // protected $source_constraints;
    // protected $dest_constraints;


    ////////////////////////////////////////////////////////////////////////

    public function __construct(array $opts=[]) {
        if (isset($opts['source_id'])) { $this->setSourceID($opts['source_id']); }
        if (isset($opts['dest_id'])) { $this->setDestID($opts['dest_id']); }

        // if (isset($opts['source_update_query'])) { $this->setSourceUpdateQuery($opts['source_update_query']); }
        // if (isset($opts['dest_update_query'])) { $this->setDestUpdateQuery($opts['dest_update_query']); }

        if (isset($opts['source_constraints'])) { $this->setSourceConstraints($opts['source_constraints']); }
        if (isset($opts['dest_constraints'])) { $this->setDestConstraints($opts['dest_constraints']); }
    }




    public function setSourceID($source_id) {
        $this->source_id = $source_id;
        return $this;
    }
    public function getSourceID() {
        return $this->source_id;
    }

    public function setDestID($dest_id) {
        $this->dest_id = $dest_id;
        return $this;
    }

    public function getDestID() {
        return $this->dest_id;
    }








    public function setSourceUpdateQuery(array $source_update_query) {
        $this->source_update_query = $source_update_query;
        return $this;
    }

    public function getSourceUpdateQuery() {
        return $this->source_update_query;
    }

    public function setDestUpdateQuery(array $dest_update_query) {
        $this->dest_update_query = $dest_update_query;
        return $this;
    }

    public function getDestUpdateQuery() {
        return $this->dest_update_query;
    }




    // public function setSourceConstraints(array $source_constraints) {
    //     $this->source_constraints = $source_constraints;
    //     return $this;
    // }

    // public function getSourceConstraints() {
    //     return $this->source_constraints;
    // }

    // public function setDestConstraints(array $dest_constraints) {
    //     $this->dest_constraints = $dest_constraints;
    //     return $this;
    // }

    // public function getDestConstraints() {
    //     return $this->dest_constraints;
    // }





    public function isValid() {
        if ($this->source_id == null) { return false; };
        if ($this->dest_id == null) { return false; };
        if ($this->source_update_query == null) { return false; };
        if ($this->dest_update_query == null) { return false; };

        return true;
    }

    ////////////////////////////////////////////////////////////////////////

}

