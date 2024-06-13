<?php

class DataType {
    const BYTE = 1;
    const CHAR = -1;
    const WORD = 2;
    const DWORD = 4;
    const INT = -4;
    const I16 = -2;
    const STR = 5;
    const HEX6 = 6;
    const COLOR = 7;
}

class BinaryParser {
    private $data;
    private $structure;
    private $offset;
    private $parsedData;
    private $callback;

    public function __construct($data, $structure, $callback = null) {
        $this->data = $data;
        $this->structure = $structure;
        $this->offset = 0;
        $this->parsedData = $this->parseStructure($structure);
        $this->callback = $callback;
    }

    public function parse() {
        return $this->parsedData;
    }

    private function parseStructure($structure) {
        $result = [];
        foreach ($structure as $key => $type) {
            $value = null;
            $saveOffset = $this->offset;
            $isPointer = strpos($key, '#') === 0;
            $match = [];
            $count = 1;
            $multiple = [];

            if (preg_match('/(?<key>[^:]+):(?<count>\d+)$/', $key, $match)) {
                $key = $match['key'];
                $count = (int)$match['count'];
            }

            if ($isPointer) {
                $key = substr($key, 1);
            }

            if ($count > 1) {
                $value = [];
                if ($isPointer) {
                    $this->offset = unpack('V', substr($this->data, $saveOffset, 4))[1];
                }
                while ($count--) {
                    $value[] = is_array($type) ? $this->parseStructure($type) : $this->parseValue($type);
                }
                if ($isPointer) {
                    $this->offset = $saveOffset + 4;
                }
            } else {
                if ($isPointer) {
                    $this->offset = unpack('V', substr($this->data, $saveOffset, 4))[1];
                    $value = is_array($type) ? $this->parseStructure($type) : $this->parseValue($type);
                    $this->offset = $saveOffset + 4;
                } else {
                    $value = is_array($type) ? $this->parseStructure($type) : $this->parseValue($type);
                }
            }

            $result[$key] = $value;
        }
        return $result;
    }

    private function parseValue($type) {
        if (is_callable($type)) {
            return $type($this->data, $this->offset);
        }

        $value = null;
        switch ($type) {
            case DataType::BYTE:
                $value = ord($this->data[$this->offset]);
                $this->offset += 1;
                break;
            case DataType::CHAR:
                $value = chr(ord($this->data[$this->offset]));
                $this->offset += 1;
                break;
            case DataType::STR:
                $value = $this->readString();
                break;
            case DataType::WORD:
                $value = unpack('v', substr($this->data, $this->offset, 2))[1];
                $this->offset += 2;
                break;
            case DataType::DWORD:
                $value = unpack('V', substr($this->data, $this->offset, 4))[1];
                $this->offset += 4;
                break;
            case DataType::INT:
                $value = unpack('l', substr($this->data, $this->offset, 4))[1];
                $this->offset += 4;
                break;
            case DataType::I16:
                $value = unpack('v', substr($this->data, $this->offset, 2))[1];
                $this->offset += 2;
                break;
            case DataType::HEX6:
                $value = unpack('V', substr($this->data, $this->offset, 4))[1];
                $value = str_pad(dechex($value), 6, '0', STR_PAD_LEFT);
                $this->offset += 4;
                break;
            case DataType::COLOR:
                $value = unpack('V', substr($this->data, $this->offset, 4))[1];
                $value = '#' . str_pad(dechex($value), 6, '0', STR_PAD_LEFT);
                $this->offset += 4;
                break;
            default:
                throw new Exception("Unknown type: $type");
        }

        return $value;
    }

    private function readString() {
        $str = '';
        while ($this->offset < strlen($this->data)) {
            $char = ord($this->data[$this->offset++]);
            if ($char === 0) break;
            $str .= chr($char);
        }
        return $str;
    }

    public function writeValue($type, $value) {
        switch ($type) {
            case DataType::BYTE:
                $this->data[$this->offset] = chr($value);
                $this->offset += 1;
                break;
            case DataType::CHAR:
                $this->data[$this->offset] = chr($value);
                $this->offset += 1;
                break;
            case DataType::STR:
                foreach (str_split($value) as $char) {
                    $this->data[$this->offset++] = chr(ord($char));
                }
                $this->data[$this->offset++] = chr(0);
                break;
            case DataType::WORD:
                $packed = pack('v', $value);
                $this->data = substr_replace($this->data, $packed, $this->offset, 2);
                $this->offset += 2;
                break;
            case DataType::DWORD:
                $packed = pack('V', $value);
                $this->data = substr_replace($this->data, $packed, $this->offset, 4);
                $this->offset += 4;
                break;
            case DataType::INT:
                $packed = pack('l', $value);
                $this->data = substr_replace($this->data, $packed, $this->offset, 4);
                $this->offset += 4;
                break;
            case DataType::I16:
                $packed = pack('v', $value);
                $this->data = substr_replace($this->data, $packed, $this->offset, 2);
                $this->offset += 2;
                break;
            case DataType::HEX6:
                $value = hexdec($value);
                $packed = pack('V', $value);
                $this->data = substr_replace($this->data, $packed, $this->offset, 4);
                $this->offset += 4;
                break;
            case DataType::COLOR:
                $value = hexdec(substr($value, 1));
                $packed = pack('V', $value);
                $this->data = substr_replace($this->data, $packed, $this->offset, 4);
                $this->offset += 4;
                break;
            default:
                throw new Exception("Unknown type: $type");
        }

        if ($this->callback) {
            call_user_func($this->callback, $this->parsedData);
        }
    }
}
?>
