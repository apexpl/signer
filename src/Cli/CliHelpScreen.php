<?php
declare(strict_types = 1);

namespace Apex\Signer\Cli;

use Apex\Signer\Cli\CLi;

/**
 * CLI Help Screen
 */
class CliHelpScreen
{

    // Properties
    private string $params_title = 'PARAMETERS';

    /**
     * Constructor
     */
    public function __construct(
        private string $title, 
        private string $usage, 
        private string $description = '',
        private array $params = [], 
        private array $flags = [], 
        private array $examples = []
    ) { 

    }

    /**
     * Set title
     */
    public function setTitle(string $title):void
    {
        $this->title = $title;
    }

    /**
     * Set usage
     */
    public function setUsage(string $usage):void
    {
        $this->usage = $usage;
    }

    /**
     * Set description
     */
    public function setDescription(string $desc):void
    {
        $this->description = $desc;
    }

    /**
     * Add param
     */
    public function addParam(string $param, string $description):void
    {
        $this->params[$param] = $description;
    }

    /**
     * Add flag
     */
    public function addFlag(string $flag, string $description):void
    {
        $this->flags[$flag] = $description;
    }

    /**
     * Add example
     */
    public function addExample(string $example):void
    {
        $this->examples[] = $example;
    }

    /**
     * Set params title
     */
    public function setParamsTitle(string $title):void
    {
        $this->params_title = $title;
    }

    /**
     * Render
     */
    public function render(Cli $cli):void
    {

        // Initialize
        $this->cli = $cli;

        // Send header
        $cli->sendHeader($this->title);

        // Send usage and description
        $cli->send("USAGE \r\n    $this->usage\r\n\r\n");
        if ($this->description != '') { 
            $cli->send("DESCRIPTION\r\n");
            $cli->send("    " . wordwrap($this->description, 75, "\r\n    ") . "\r\n\r\n");
        }

        // Params
        if (count($this->params) > 0) { 
            $cli->send($this->params_title . "\r\n\r\n");
            $this->renderArray($this->params);
        }

        // Flags
        if (count($this->flags) > 0) { 
            $cli->send("OPTIONAL FLAGS\r\n\r\n");
            $this->renderArray($this->flags);
        }

        // Examples
        if (count($this->examples) > 0) { 
            $cli->send("EXAMPLES\r\n\r\n");
            foreach ($this->examples as $example) { 
                $cli->send("    $example\r\n\r\n");
            }
        }

        // End
        $cli->send("-- END --\r\n\r\n");
    }

    /**
     * Render array
     */
    public function renderArray(array $inputs):void
    {

        // Get max size
        $size = 0;
        foreach ($inputs as $key => $value) { 
            $size = strlen($key) > $size ? strlen($key) : $size;
        }
        $size += 4;

        // Go through inputs
        foreach ($inputs as $key => $value) { 
            $break = "\r\n" . str_pad('', ($size + 4), ' ', STR_PAD_RIGHT);
            $line = '    ' . str_pad($key, $size, ' ', STR_PAD_RIGHT) . wordwrap($value, (75 - $size - 4), $break);
            $this->cli->send("$line\r\n");
        }
        $this->cli->send("\r\n");
    }

}


