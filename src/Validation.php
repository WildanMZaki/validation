<?php

/**
 * Library ini digunakan untuk validasi data input berdasarkan rule-rule tertentu
 * dan otomatis meresponse json ketika ditemukan data yang tidak valid
 * 
 * Author: Wildan M Zaki
 * 09/05/2024
 */
defined('BASEPATH') or exit('No direct script access allowed');

class CI3
{
    protected $CI;

    // Processing property
    private $key;
    private $alias;
    private $value;
    private $rule;
    private $ruleValue;
    private $isNumeric;

    private $allCorrect = true;
    private $errors = [];

    private $autoResponse = true;
    private $defaultErrorMessage = 'Masih terdapat data yang salah';

    // Default error message
    protected static $messages = [
        'required' => '{label} tidak boleh kosong',
        'numeric' => '{label} harus berupa angka',
        'min' => '{label} harus setidaknya {value} karakter',
        'max' => '{label} tidak boleh lebih dari {value} karakter',
        'in' => '{label} harus memiliki nilai di antara {value}',
    ];

    public function __construct()
    {
        $this->CI = &get_instance();
    }

    public function setAutoResponse(bool $active = true): self
    {
        $this->autoResponse = $active;
        return $this;
    }

    public function setErrorMessage($message): self
    {
        if (!is_string($message)) throw new Exception('Error message default harus string');
        $this->defaultErrorMessage = $message;
        return $this;
    }

    public function validate($rules, $messages = [])
    {
        self::$messages = array_merge(self::$messages, $messages);
        try {
            foreach ($rules as $input => $ruleset) {
                [$label, $alias] = array_pad(explode(':', $input), 2, null);
                $this->key = $label;
                $this->alias = $alias;

                $this->isNumeric = strpos($ruleset, 'numeric') !== false;

                // Nilai yang diinputkan user berdasarkan key/label-nya
                $this->value = getRequest($this->key);

                foreach (explode('|', $ruleset) as $rule) {
                    if (isset($this->errors[$this->key])) break;
                    [$r, $ruleValue] = array_pad(explode(':', $rule), 2, true);
                    $this->rule = $r;

                    if ($ruleValue !== true) {
                        $ruleValues = explode(',', $ruleValue);
                        $ruleValue = count($ruleValues) === 1 ? $ruleValues[0] : $ruleValues;
                    }
                    $this->ruleValue = $ruleValue;
                    $this->isValid();
                }
            }
            if (!$this->allCorrect) throw new ValidationError($this->defaultErrorMessage);
            return true;
        } catch (ValidationError $er) {
            responseJSON([
                'success' => false,
                'message' => $er->getMessage(),
                'errors' => $this->errors,
            ], 400);
        } catch (Exception $th) {
            throw $th;
        }
    }

    public function isValid()
    {
        $methodName = $this->rule;
        if (method_exists($this, $methodName)) {
            $success = $this->$methodName();
            // if (!$success) throw new ValidationError($this->getMsg());
            if (!$success) {
                $this->allCorrect = false;
                $msg = $this->getMsg();
                $this->errors[$this->key] = $msg;
                if ($this->autoResponse) throw new ValidationError($msg);
            }
        } else {
            throw new LibraryError("No validation method with name '$methodName' in this library. This is called in '{$this->key}' input");
        }
    }

    private function getMsg()
    {
        $label = ucfirst(is_null($this->alias) ? $this->key : $this->alias);
        $specific = "{$this->key}.{$this->rule}";
        $general = $this->rule;
        $key = isset(self::$messages[$specific]) ? $specific : $general;
        $message = isset(self::$messages[$key]) ? self::$messages[$key] : 'Terjadi kesalahan';

        $message = str_replace('{label}', $label, $message);

        // $replace = is_string($this->ruleValue) ? $this->ruleValue : (is_array($this->ruleValue) ? implode(', ', $this->ruleValue) : 'undefined');
        $message = str_replace('{value}', $this->getReplaceValue(), $message);

        return $message;
    }

    private function getReplaceValue(): string
    {
        if (is_string($this->ruleValue)) {
            return $this->ruleValue;
        } else if (is_array($this->ruleValue)) {
            $values = $this->ruleValue;
            if (count($values) == 1) return $values[0];
            if (count($values) == 2) return "{$values[0]} dan $values[1]";
            $li = count($values) - 1;
            $last = $values[$li];
            unset($values[$li]);
            return implode(', ', $values) . ", dan $last";
        } else {
            return 'undefined';
        }
    }

    public function required()
    {
        $input = $this->value;
        if ($input == null || trim($input) == "") {
            return false;
        }

        return true;
    }

    public function numeric()
    {
        return is_numeric($this->value);
    }

    public function ruleValueMustNumeric(): void
    {
        if (!is_numeric($this->ruleValue)) throw new LibraryError("Nilai untuk aturan '{$this->rule}' harus angka, nilai saat ini '{$this->ruleValue}'");
    }

    public function min(): bool
    {
        $this->ruleValueMustNumeric();
        return (($this->isNumeric) ? $this->value : strlen($this->value)) >= $this->ruleValue;
    }

    public function max(): bool
    {
        $this->ruleValueMustNumeric();
        return (($this->isNumeric) ? $this->value : strlen($this->value)) <= $this->ruleValue;
    }

    public function ruleValueMustArray(): void
    {
        if (!is_array($this->ruleValue)) throw new LibraryError("Nilai untuk aturan '{$this->rule}' harus array, nilai saat ini '{$this->ruleValue}'");
    }

    public function in()
    {
        $this->ruleValueMustArray();
        return in_array($this->value, $this->ruleValue);
    }
}


// Exception khusus yang dithrow hanya ketika terjadi error di proses validasi
class ValidationError extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// Exception khusus akibat kesalahan pemakaian library
class LibraryError extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
