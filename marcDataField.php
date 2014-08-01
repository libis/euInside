<?php
/**
 * User: NaeemM
 * Date: 20/05/14
 */

require_once("marcSubField.php");

class marcDataField {

    public $subField;
    public $tag;
    public $ind1;
    public $ind2;

    public function __construct(){
        $this->subField = new marcSubField();
    }
} 