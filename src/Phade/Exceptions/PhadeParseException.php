<?php
/**
 * Created by PhpStorm.
 * User: Andreas
 * Date: 2013-10-12
 * Time: 21:23
 */

namespace Phade\Exceptions;


class PhadeParseException extends PhadeException{
    /**
     * @var string
     */
    private $jade_filename;
    /**
     * @var
     */
    private $input;
    /**
     * @var int
     */
    private $jade_line;

    public function __construct($message, $filename, $line, $input, $prevEx = null) {
        parent::__construct($message,0, $prevEx);
        $this->jade_filename = $filename;
        $this->jade_line = $line;
        $this->input = $input;
    }

    /**
     * @return int
     */
    public function getJadeFilename()
    {
        return $this->jade_filename;
    }

    /**
     * @return mixed
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return int
     */
    public function getJadeLine()
    {
        return $this->jade_line;
    }
} 