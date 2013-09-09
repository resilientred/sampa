<?php
/**
*
*	Framework boss (handles worker jobs)
*
*	@package sampa\Core\Boss
*	@copyright 2013 appdeck
*	@link http://github.com/appdeck/sampa
*	@version 0.1
*	@since 0.1
*	@license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
*/

namespace sampa\Core;

use sampa\Exception;

final class Boss {
	private $time;
	private $log;
	private $config;
	private $boot = false;
	private $lock = null;

	private function lock($name) {
		$this->lock = fopen(sys_get_temp_dir() . DIRECTORY_SEPARATOR . "{$name}.lock", 'w');
		if (is_resource($this->lock))
			return flock($this->lock, LOCK_EX | LOCK_NB);
		return false;
	}

	private function unlock() {
		if (is_resource($this->lock))
			flock($this->lock, LOCK_UN);
	}

	public function __construct($environment = null) {
		$this->time = microtime(true);
		//defines the environment name
		if (is_null($environment))
			define('__ENVIRONMENT__', '');
		else
			define('__ENVIRONMENT__', $environment);
	}

	public function __destruct() {
		if (is_resource($this->lock))
			fclose($this->lock);
		//$this->log->debug("{$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}?{$_SERVER['QUERY_STRING']} {$_SERVER['SERVER_PROTOCOL']}");
		$this->log->debug('TIME: ' . Formater::msec(microtime(true) - $this->time));
		$this->log->debug('RAM: ' . Formater::size(memory_get_peak_usage(true)));
	}

	public function boot($config = null, $log = null) {
		//overrides the default config folder
		if (!is_null($config)) {
			$config = realpath($config);
			if ($config !== false) {
				if (substr_compare($config, '/', -1, 1) != 0)
					$config .= '/';
				define('__CFG__', $config);
			}
		}
		//overrides the default log folder
		if (!is_null($log)) {
			$log = realpath($log);
			if ($log !== false) {
				if (substr_compare($log, '/', -1, 1) != 0)
					$log .= '/';
				define('__LOG__', $log);
			}
		}
		//defines the base path to framework
		define('__SAMPA__', dirname(dirname(__FILE__)));
		foreach (array('cfg', 'log', 'tpl') as $folder) {
			$key = '__' . strtoupper($folder) . '__';
			$path = realpath(__SAMPA__ . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR);
			if ($path === false)
				$path = __SAMPA__;
			if (!defined($key))
				define($key, $path . DIRECTORY_SEPARATOR);
		}
		//loads the framework's configuration
		$this->config = Config::singleton();
		//sets output and internal encoding
		$encoding = $this->config->read('framework/main/encoding', 'UTF-8');
		mb_internal_encoding($encoding);
		//sets the error display
		if ($this->config->read('framework/main/debug', true))
			ini_set('display_errors', 1);
		else
			ini_set('display_errors', 0);
		error_reporting(-1);
		//sets the general error handler
		set_error_handler(array($this, 'logger'));
		//sets the proper include path for shared hosting
		$include = $this->config->read('framework/main/include_path', '');
		if (!empty($include))
			set_include_path($include);
		//sets the default timezone
		date_default_timezone_set($this->config->read('framework/main/timezone', 'UTC'));
		//loads the log handler
		$logfile = __LOG__ . date('Ymd') . '-boss.log';
		$this->log = new Log($logfile, $this->config->read('framework/log/level', Log::DISABLED), $this->config->read('framework/log/buffered', true));
		$this->boot = true;
	}

	public function dispatch($argc, $argv) {
		if (!$this->boot)
			$this->boot();
		if ($argc < 2)
			throw new Exception\Worker('Missing worker name');
		if (!preg_match('/[a-zA-Z0-9-_]+/', $argv[1]))
			throw new Exception\Worker("Invalid worker name '{$argv[1]}'");
		if ($argc == 2) {
			$argc++;
			$argv[2] = 'start';
		}
		$class = 'Worker\\' . ucfirst($argv[1]) . 'Worker';
		if (!class_exists($class))
			throw new Exception\Worker("Worker not found ({$class})");
		switch ($argv[2]) {
			case 'start':
				if (!$this->lock($argv[1]))
					exit;
				echo 'worker started (' . date('d/m/Y H:i:s') . ")\n";
				echo "worker name: {$argv[1]}\n";
				$pid = getmypid();
				echo "worker pid: {$pid}\n";
				$pidf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "{$argv[1]}.pid";
				@file_put_contents($pidf, $pid);
				$time = microtime(true);
				$worker = new $class;
				$worker->run(array_slice($argv, 3));
				$time = (microtime(true) - $time);
				echo 'worker finished (' . Formater::msec($time) . ")\n";
				@unlink($pidf);
				$this->unlock();
				break;
			case 'stop':
				$pidf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "{$argv[1]}.pid";
				if ((!$this->lock($argv[1])) && (is_file($pidf))) {
					echo "worker stop\n";
					$pid = @file_get_contents($pidf);
					echo "worker pid: {$pid}\n";
					exec("kill -9 {$pid}");
				} else
					echo "worker not running\n";
				break;
			default:
				throw new Exception\Worker("Invalid operation '{$argv[2]}'");
		}
	}

	public function logger($num, $str, $file, $line, $context) {
		$msg = "{$str} in {$file}:{$line}";
		switch ($num) {
			case E_ERROR:
			case E_USER_ERROR:
				$this->log->error($msg);
				break;
			case E_WARNING:
			case E_USER_WARNING:
				$this->log->warning($msg);
				break;
			case E_NOTICE:
			case E_USER_NOTICE:
				$this->log->notice($msg);
				break;
			default:
				$this->log->alert($msg);
		}
		return true;
	}

}
