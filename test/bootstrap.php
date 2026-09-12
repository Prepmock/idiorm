<?php

require_once dirname(__FILE__) . '/../idiorm.php';

/**
 *
 * Mock version of the PDOStatement class.
 *
 * The method signatures copy PDOStatement's own, types included. PHP 8.1 gave
 * the internal classes tentative return types, so an override written without
 * them raises a deprecation on every test run — noise that would bury a real
 * deprecation coming from idiorm itself, which is what this suite exists to catch.
 *
 */
class MockPDOStatement extends PDOStatement {
   private $current_row = 0;
   private $statement = NULL;
   private $bindParams = array();

   /**
    * Store the statement that gets passed to the constructor
    */
   public function __construct($statement) {
       $this->statement = $statement;
   }

   /**
    * Check that the array
    *
    * Returns true, as a real statement does on success. The untyped version
    * returned null, which only went unnoticed because nothing asserted on it.
    */
   public function execute(?array $params = null): bool {
       $count = 0;
       $m = array();
       if (is_null($params)) $params = $this->bindParams;
       if (preg_match_all('/"[^"\\\\]*(?:\\?)[^"\\\\]*"|\'[^\'\\\\]*(?:\\?)[^\'\\\\]*\'|(\\?)/', $this->statement, $m, PREG_SET_ORDER)) {
           $count = count($m);
           for ($v = 0; $v < $count; $v++) {
               if (count($m[$v]) == 1) unset($m[$v]);
           }
           $count = count($m);
           for ($i = 0; $i < $count; $i++) {
               if (!isset($params[$i])) {
                   ob_start();
                   var_dump($m, $params);
                   $output = ob_get_clean();
                   throw new Exception('Incorrect parameter count. Expected ' . $count . ' got ' . count($params) . ".\n" . $this->statement . "\n" . $output);
               }
           }
       }
       return true;
   }

   /**
    * Add data to arrays
    */
   public function bindParam(string|int $param, mixed &$var, int $type = PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
   {
       // Do check on type
       if ($type != PDO::PARAM_STR && $type != PDO::PARAM_NULL && $type != PDO::PARAM_BOOL && $type != PDO::PARAM_INT)
           throw new Exception('Incorrect parameter type. Expected $type to be a PDO::PARAM_* constant.');

       // Add param to array
       $this->bindParams[is_int($param) ? --$param : $param] = $var;
       return true;
   }

   /**
    * Return some dummy data
    */
   public function fetch(int $mode = PDO::FETCH_BOTH, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
       if ($this->current_row == 5) {
           return false;
       } else {
           return array('name' => 'Fred', 'age' => 10, 'id' => ++$this->current_row);
       }
   }
}

/**
 * Another mock PDOStatement class, used for testing multiple connections
 */
class MockDifferentPDOStatement extends MockPDOStatement { }

/**
 *
 * Mock database class implementing a subset
 * of the PDO API.
 *
 */
class MockPDO extends PDO {

   /**
    * Declared rather than created on first assignment: PHP 8.2 deprecates
    * dynamic properties.
    */
   public $last_query;

   /**
    * Return a dummy PDO statement
    */
   public function prepare(string $query, array $options = array()): PDOStatement|false {
       $this->last_query = new MockPDOStatement($query);
       return $this->last_query;
   }
}

/**
 * A different mock database class, for testing multiple connections
 * Mock database class implementing a subset of the PDO API.
 */
class MockDifferentPDO extends MockPDO {

    /**
     * Return a dummy PDO statement
     */
    public function prepare(string $query, array $options = array()): PDOStatement|false {
        $this->last_query = new MockDifferentPDOStatement($query);
        return $this->last_query;
    }
}

class MockMsSqlPDO extends MockPDO {

   public $fake_driver = 'mssql';

   /**
    * If we are asking for the name of the driver, check if a fake one
    * has been set.
    */
    public function getAttribute(int $attribute): mixed {
        if ($attribute == self::ATTR_DRIVER_NAME) {
            if (!is_null($this->fake_driver)) {
                return $this->fake_driver;
            }
        }

        return parent::getAttribute($attribute);
    }

}
