<?php
declare(strict_types = 1);

namespace Apex\Signer\Tests\Stubs;

use Apex\Signer\Utils\AbstractUtils;

/**
 * Handles all CLI functionality for for Apex
 */
class CliStub extends AbstractUtils
{

    // Properties
        protected static array $argv = [];
    protected static string $stdout = '';
    protected static string $stderr = '';
    protected static array $inputs = [];
    protected static int $input_num = 0;
    protected static bool $do_confirm = true;
    public string $username;
    public string $confdir;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initializeConfDir();
    }

    /**
     * Run CLI command
     */
    public function run(array $args, array $inputs = [], bool $do_confirm = true):string
    {

        // Set inputs
        self::$inputs = $inputs;
        self::$input_num = 0;
        self::$do_confirm = $do_confirm;
        self::$argv = $args;
        self::$stdout = '';

        // Check for help
        $is_help = false;
        if ($args[0] == 'help' || $args[0] == 'h') { 
            $is_help = true;
            array_shift($args);
        }
        $method = str_replace('-', '_', ($args[0] ?? ''));

        // Check for command
        $class_name = "\\Apex\\Signer\\Cli\\Commands\\" . ucwords($method);
        if (!class_exists($class_name)) { 
            $this->showHelp();
            return '';
        }

        // Load sub class
        $cmd = new $class_name();
        $method = $is_help === true ? 'help' : 'process';
        $cmd->$method($this, $args);

        // Return
        return self::$stdout;
    }

    /**
     * Show help
     */
    public function sendHelp(string $title = '', string $usage = '', string $desc = '', array $params = [], array $flags = []):void
    {

        // Send header
        $this->sendHeader($title);

        // Send usage and description
        if ($usage != '') { 
            $this->send("USAGE \r\n    ./apex $usage\r\n\r\n");
        }
        if ($desc != '') {
            $this->send("DESCRIPTION\r\n    " . wordwrap($desc, 75, "\r\n    ") . "\r\n\r\n");
        }

        // Params
        if (count($params) > 0) { 

            // Get max size
            $size = 0;
            foreach ($params as $key => $value) { 
                $size = strlen($key) > $size ? strlen($key) : $size;
            }
            $size += 4;

            $this->send("PARAMETERS\r\n\r\n");
            foreach ($params as $key => $value) {
                $break = "\r\n" . str_pad('', ($size + 4), ' ', STR_PAD_RIGHT);
                $line = '    ' . str_pad($key, $size, ' ', STR_PAD_RIGHT) . wordwrap($value, (75 - $size - 4), $break);
                $this->send("$line\r\n");
            }
            $this->send("\r\n");
        }

        // Flags
        if (count($flags) > 0) { 

            // Get max size
            $size = 0;
            foreach ($flags as $key => $value) { 
                $size = strlen($key) > $size ? strlen($key) : $size;
            }
            $size += 4;

            $this->send("OPTIONAL FLAGS\r\n\r\n");
            foreach ($flags as $key => $value) { 
                $break = "\r\n" . str_pad('', ($size + 6), ' ', STR_PAD_RIGHT);
                $line = '    --' . str_pad($key, $size, ' ', STR_PAD_RIGHT) . wordwrap($value, (75 - $size - 6), $break);
                $this->send("$line\r\n");
            }
            $this->send("\r\n");
        }
        $this->send("-- END --\r\n\r\n");
    }

    /**
     * Get command line arguments and options
     */
    public function getArgs(array $has_value = []):array
    {

        // Initialize
        list($args, $options, $tmp_args) = [[], [], self::$argv];

        // Go through args
        while (count($tmp_args) > 0) { 
            $var = array_shift($tmp_args);

            // Long option with =
            if (preg_match("/^-{1,2}(\w+?)=(.+)$/", $var, $match)) { 
                $options[$match[1]] = $match[2];

            } elseif (preg_match("/^-{1,2}(.+)$/", $var, $match) && in_array($match[1], $has_value)) { 


                $value = isset($tmp_args[0]) ? array_shift($tmp_args) : '';
                if ($value == '=') { 
                    $value = isset($tmp_args[0]) ? array_shift($tmp_args) : '';
                }
                $options[$match[1]] = $value;

            } elseif (preg_match("/^--(.+)/", $var, $match)) { 
                $options[$match[1]] = true;

            } elseif (preg_match("/^-(\w+)/", $var, $match)) { 
                $chars = str_split($match[1]);
                foreach ($chars as $char) { 
                    $options[$char] = true;
                }

            } else { 
                $args[] = $var;
            }
        }

        // Return
        return array($args, $options);
    }

    /**
     * Get input from the user.
     */
    public function getInput(string $label, string $default_value = '', bool $is_secret = false):string
    {

        // Get value
        $name = trim($label);
        if (isset(self::$inputs[$name])) { 
            $value = self::$inputs[$name];
        } elseif (isset(self::$inputs[self::$input_num])) { 
            $value = self::$inputs[self::$input_num];
        } elseif ($default_value != '') { 
            $value = $default_value;
        } else { 
            $value = 'undefined';
        }

        // Echo label, and return
        $this->send($label . $value . "\r\n");
        self::$input_num++;
        return $value;
    }

    /**
     * Get confirmation
     */
    public function getConfirm(string $message, string $default = ''):bool
    {
        $value = self::$do_confirm === true ? 'y' : 'n';
        $this->send($message . " (yes/no) [$default]: $value\r\n");
        return self::$do_confirm;
    }

    /**
     * Get password
     */
    public function getNewPassword(string $label = 'Desired Password', bool $allow_empty = false, int $min_score = 2):?string
    {

        // Get password
        $password = $this->getInput($label . ': ', '', true);
        $confirm = $this->getInput('Confirm Password: ', '', true);

        // Return
        return $password == '' ? null : $password;
    }

    /**
     * Get option from list
     */
    public function getOption(string $message, array $options, string $default_value = '', bool $add_numbering = false):string
    {

        // Set message
        $map = [];
        $message .= "\r\n\r\n";

        // Go through options
        $x=0;
        foreach ($options as $key => $name) { 
            if ($add_numbering === true) { 
                $map[(string) ++$x] = $key;
                if ($key == $default_value) { 
                    $default_value = (string) $x;
                }
                $key = (string) $x;
            }
            $message .= "    [$key] $name\r\n";
        }
        $message .= "\r\nChoose One [$default_value]: ";
        if ($add_numbering === true) { 
            $options = $map;
        }

        // Get option
        do {
            $opt = $this->getInput($message, $default_value);
            if (isset($options[$opt])) { 
            break;
            }
            $this->send("Invalid option, please try again.  ");
        } while (true);

        // Get mapped option, if needed
        if ($add_numbering === true) { 
            $opt = $map[$opt];
        }

        // Return
        return $opt;
    }

    /**
     * Send output to user.
     */
    public function send(string $data):void
    {

        // Wordwrap,  if needed
        if (!preg_match("/^\s/", $data)) { 
            $data = wordwrap($data, 75, "\r\n");
        }

        // Output data
        self::$stdout .= $data;
    }

    /**
     * Send header to user
     */
    public function sendHeader(string $label):void
    {
        $this->send("------------------------------\r\n");
        $this->send("-- $label\r\n");
        $this->send("------------------------------\r\n\r\n");
    }

    /**
     * Display table
     */
    public function sendTable(array $rows):void
    {

        // Return, if no rows
        if (count($rows) == 0) { 
            return;
        }

        // Get column sizes
        $sizes = [];
        for ($x=0; $x < count($rows[0]); $x++) { 

            // Get max length
            $max_size = 0;
            foreach ($rows as $row) { 
                if (strlen($row[$x]) > $max_size) { $max_size = strlen($row[$x]); }
            }
            $sizes[$x] = ($max_size + 3);
        }
        $total_size = array_sum(array_values($sizes));

        // Display rows
        $first = true;
        foreach ($rows as $row) { 

            // Go through fields
            list($x, $line) = [0, ''];
            foreach ($row as $var) { 
                $line .= str_pad(' ' . $var, ($sizes[$x] - 1), ' ', STR_PAD_RIGHT) . '|';
            $x++; }

            // Display line
            $this->send("$line\r\n");
            if ($first === true) { 
                $this->send($line = str_pad('', $total_size, '-') . "\r\n");
                $first = false;
            }
        }
        $this->send("\r\n");
    }

    /**
     * Success
     */
    public function success(string $message, array $files = []):void
    {
        $this->send("\r\n$message\r\n\r\n");
        foreach ($files as $file) { 
            $this->send("    /$file\r\n");
        }
        $this->send("\r\n");
    }

    /**
     * Error
     */
    public function error(string $message)
    {
        $this->send("ERROR: $message\r\n");
    }

    /**
     * Reset
     */
    public function reset():void
    {

        $confdir = $this->getConfDir();
        if (is_dir($confdir)) { 
            system("rm -rf $confdir");
            $this->initializeConfDir();
        }

        if (file_exists(SITE_PATH . '/signatures.json')) { 
            unlink(SITE_PATH . '/signatures.json');
        }

        if (file_exists(SITE_PATH . '/' . $this->username . '.pem')) { 
            unlink(SITE_PATH . '/' . $this->username . '.pem');
        }

    }

    /**
     * Initialize conf dir
     */
    private function initializeConfDir():void
    {

        // Check /keys/ directory
        $this->confdir = $this->getConfDir();
        $this->username = $this->getPackagistUsername();
        if (!is_dir("$this->confdir/keys")) { 
            mkdir("$this->confdir/keys", 0755, true);
        }

        // Check /certs/ directory
        if (!is_dir("$this->confdir/certs")) { 
            mkdir("$this->confdir/certs", 0755, true);
        }

    }


}

