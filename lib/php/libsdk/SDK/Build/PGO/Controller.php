<?php

namespace SDK\Build\PGO;

use SDK\{Config as SDKConfig, Exception};
use SDK\Build\PGO\Config as PGOConfig;
use SDK\Build\PGO\Server\{MariaDB, NGINX};
use SDK\Build\PGO\Server\PHP;

/* TODO add bench action */

class Controller
{
	protected $cmd;
	protected $scenario;
	protected $conf;

	public function __construct(string $cmd, ?string $scenario)
	{
		$this->cmd = $cmd;

		if (NULL == $scenario) {
			$scenario = "default";
		}
		$this->scenario = $scenario;
	}

	public function handle()
	{
		$this->conf = new PGOConfig("init" !== $this->cmd);

		switch ($this->cmd) {
		default:
			throw new Exception("Unknown action '{$this->cmd}'.");
			break;
		case "init":
			$this->init();
			break;
		case "train":
			$this->train();
			break;
		case "up":
			$this->up();
			break;

		case "down":
			$this->down();
			break;
		}
	}

	public function init()
	{
		echo "Initializing PGO training environment.\n";

		$work_dir = $this->conf->getWorkDir();
		if (!is_dir($work_dir)) {
			if (!mkdir($work_dir)) {
				throw new Exception("Failed to create work dir '$work_dir'.");
			}
		}

		$srv_dir = $this->conf->getSrvDir();
		if (!is_dir($srv_dir)) {
			if (!mkdir($srv_dir)) {
				throw new Exception("Failed to create '$srv_dir'.");
			}
		}

		$htdocs = $this->conf->getHtdocs();
		if (!is_dir($htdocs)) {
			if (!mkdir($htdocs)) {
				throw new Exception("Failed to create '$htdocs'.");
			}
		}

		$nginx = new NGINX($this->conf);
		$nginx->init();

		$maria = new MariaDB($this->conf);
		$maria->init();

		$php_fcgi_tcp = new PHP\FCGI($this->conf, true, $maria, $nginx, $this->scenario);
		$php_fcgi_tcp->init();

		$this->conf->dump();

		echo "Initialization complete.\n";
	}

	public function isInitialized()
	{
		$base = getenv("PHP_SDK_ROOT_PATH");

		/* XXX Could be some better check. */
		return is_dir($base . DIRECTORY_SEPARATOR . "pgo" . DIRECTORY_SEPARATOR . "work");
	}

	public function train()
	{
		if (!$this->isInitialized()) {
			throw new Exception("PGO training environment is not initialized.");
		}

		echo "Starting PGO training.\n";
		$this->up();

		/* do work here */

		$this->down();
		echo "PGO training finished.\n";
	}

	public function up()
	{

		if (!$this->isInitialized()) {
			throw new Exception("PGO training environment is not initialized.");
		}
		echo "Starting up PGO environment.\n";

		$nginx = new NGINX($this->conf);
		$nginx->up();

		$maria = new MariaDB($this->conf);
		$maria->up();

		$php_fcgi_tcp = new PHP\FCGI($this->conf, true, $maria, $nginx, $this->scenario);
		$php_fcgi_tcp->up();

		echo "The PGO environment is up.\n";
	}

	public function down(bool $force = false)
	{
		if (!$this->isInitialized()) {
			throw new Exception("PGO training environment is not initialized.");
		}
		/* XXX check it was started of course. */
		echo "Shutting down PGO environment.\n";

		$nginx = new NGINX($this->conf);
		$nginx->down($force);

		$maria = new MariaDB($this->conf);
		$maria->down($force);

		$php_fcgi_tcp = new PHP\FCGI($this->conf, true, $maria, $nginx, $this->scenario);
		$php_fcgi_tcp->down($force);

		echo "The PGO environment has been shut down.\n";
	}
}
