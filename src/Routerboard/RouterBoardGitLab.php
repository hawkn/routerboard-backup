<?php

namespace Src\RouterBoard;

use Src\RouterBoard\GitLabAPI;
use Src\RouterBoard\SSHConnector;
use Exception;

class RouterBoardGitLab extends AbstractRouterBoard  implements IRouterBoardBackup {
	
	private $gitlab;
	private $dbconnect; 
	private $ssh;
	private $rootdir;
	private $filename;
	private $folder;
	
	public function __construct($config, $logger) {
		parent::__construct($config, $logger);
		$this->gitlab = new GitLabAPI( $this->config, $this->logger );
		$this->checkExistProject( $this->config['gitlab']['project-name'] );
		$this->dbconnect = new $this->config['database']['data-adapter']($this->config, $this->logger);
		$this->ssh = new SSHConnector($this->config, $this->logger);
		$this->rootdir = $this->config['routerboard']['backupuser'];
		$this->filename = $this->rootdir;
		$this->folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->rootdir . DIRECTORY_SEPARATOR;
	}
	
	/**
	 * @see \Src\RouterBoard\IRouterBoardBackup::backupAllRouterBoards()
	 */
	public function backupAllRouterBoards() {
		if ( $result = $this->dbconnect->getIP() ) {
			foreach ($result as $data) {
				// get backup file from routerboard
				if ( $this->ssh->getBackupFile($data['addr'], $this->rootdir, $this->folder, $data['identity']) ) {
					// push both to the repository
					$this->doGitLabPush( $data['addr'], $data['identity'], 'backup', 'base64');
					$this->doGitLabPush( $data['addr'], $data['identity'], 'rsc', 'text');
					$this->logger->log( "Backup of the router " . $data['addr'] . " has been sucessfully." );
					$this->dbconnect->updateBackupTime( $data['addr'] );
				}
			}
			return;
		}
		$this->logger->log('Get IP addresses from the database failed! Backup is not available. Try later.', $this->logger->setError() );
		$this->sendMail();
		
	}
	
	/**
	 * @see \Src\RouterBoard\IRouterBoardBackup::backupOneRouterBoard()
	 */
	public function backupOneRouterBoard(array $addr) {
		foreach ($addr as $ip) {
			if ( $this->dbconnect->checkExistIP($ip) ) {
				$data = $this->dbconnect->getOneIP($ip);
				if ( $this->ssh->getBackupFile($data[0]['addr'], $this->rootdir, $this->folder, $data[0]['identity']) ) {
					// push both to the repository
					$this->doGitLabPush( $data[0]['addr'], $data[0]['identity'], 'backup', 'base64');
					$this->doGitLabPush( $data[0]['addr'], $data[0]['identity'], 'rsc', 'text');
					$this->logger->log( "Backup of the router " . $data[0]['addr'] . " has been sucessfully." );
					$this->dbconnect->updateBackupTime( $data[0]['addr'] );
					continue;
				}
			}
			$this->logger->log('IP addresses: ' . $ip . ' does not exist in the database! Add this IP address first.', $this->logger->setError() );
		}
		$this->sendMail();
	}

	/**
	 * GitLab Push File
	 * @param string $addr
	 * @param string $identity
	 * @param string $extension
	 * @param string $type
	 */
	private function doGitLabPush($addr, $identity, $extension, $type) {
		$rbfolder = $identity . '_' . $addr . DIRECTORY_SEPARATOR;
		$message = 'backup/change time ' . date("Y-d-m H:i.s") . ' type = ' . $type;
		if ( $type == 'base64' )
			$content = base64_encode(file_get_contents($this->folder . $rbfolder . $this->filename . '.' . $extension));
		else
			$content = file_get_contents($this->folder . $rbfolder . $this->filename . '.' . $extension);
		$this->gitlab->sendFile (
				$rbfolder . $this->filename . '.' . $extension,
				$content,
				'master',
				$message
				);
	}

	/**
	 * Check if project with $name does exist in repository, if not try create it
	 * 
	 * @param string $name
	 * @throws Exception
	 */
	protected function checkExistProject($name) {
		if ( !$this->gitlab->checkProjectName() ) {
			$this->logger->log( "Project '" . $name . "' does not exist in repo. Creating new ...", $this->logger->setNotice() );
			if ( $this->gitlab->createProject() ) {
				$this->logger->log( "Project '" . $this->config['gitlab']['project-name'] . "' has been created successfully.", $this->logger->setNotice() );
				return;
			}
			throw new Exception("Can not create new project in GitLab!");
		}
	}

	/**
	 * Send email with error if any
	 */
	private function sendMail() {
		if ( $this->config['mail']['sendmail'] && $this->logger->isMail() )
			$this->logger->send($this->config['mail']['email-from'], $this->config['mail']['email-to']);
	}
	
	
}
