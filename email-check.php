<?php
	class CheckMail {

		static $instance;

		public $timeout		= 10;
		public $port		= 25;

		static function self(){
			if(empty(static::$instance)) {
				$class = get_called_class();
				static::$instance = new $class();
				static::$instance->config = ['domain_main' => 'github.com'];
				static::$instance->localhost = static::$instance->config['domain_main'];
			}
			return static::$instance;
		}

		function check_one($email = "") {
			if (!static::is_valid_email($email)) {
				echo 'Error: not valid email - ', $email, PHP_EOL;
				return false;
			}
			$email = strtolower($email);
			$host = strtolower( substr (strstr ($email, '@'), 1) );
			$mxhosts = $mx_priorities = array();

			if (@getmxrr ($host.'.', $mxhosts, $mx_priorities) == true) {
				array_multisort ($mxhosts, $mx_priorities);
			}
			if(empty($mxhosts[0])) {
				$mxhosts = $mx_priorities = array();
			}

			$sender = 'noreply@'.$this->localhost;

			$result = false;
			$id = 0;
			$mx_count = count($mxhosts);
			$error = '';

			while (!$result && $id < $mx_count)
			{
				if (!($connection = @fsockopen($mxhosts[$id], $conn_port = 25, $errno, $error_conn, $this->timeout)))
					if (!($connection = @fsockopen($mxhosts[$id], $conn_port = 587, $errno, $error_conn, $this->timeout))) {
						$id++;
						continue;
				}

				if(!$connection) break;

				$nopes = fgets ($connection,1024);

				fputs ($connection,'HELO '.$this->localhost."\r\n"); // 250
				$data = fgets ($connection,1024);
				// echo 'HELO '.$this->localhost.' (TO '.$mxhosts[$id].':'.$conn_port.') ', $data, PHP_EOL;
				$response = substr ($data,0,1);

				if (empty($response) || !in_array($response, [2, 3]))  {
					fputs ($connection,'EHLO '.$this->localhost."\r\n"); // 250
					$data = fgets ($connection,1024);
					// echo 'EHLO TO '.$mxhosts[$id].' ('.$conn_port.') :', $data, PHP_EOL;
					$response = substr ($data,0,1);
				}

				if(empty($response)) {
					$id++;
					fclose ($connection);
					$connection = false;
					continue;
				}

				if (in_array($response, [2, 3]))  // 200, 250, 354 etc.
				{
					fputs ($connection,"MAIL FROM:<$sender>\r\n");
					$data = fgets($connection,1024);
					// echo 'MAIL FROM:<'.$sender.'> ', $data, PHP_EOL;
					$response = substr ($data,0,1);
					if (in_array($response, [2, 3])) // 200, 250, 354 etc.
					{
						fputs ($connection,"RCPT TO:<$email>\r\n");
						$data = fgets($connection,1024);
						// echo 'RCPT TO:<'.$email.'> ', $data, PHP_EOL;
						$response = substr ($data,0,1);
						if (in_array($response, [2, 3])) // 200, 250, 354 etc.
						{
							fputs ($connection,"data\r\n");
							$data = fgets($connection,1024);
							// echo 'data ', $data, PHP_EOL;
							$response = substr ($data,0,1);
							if (in_array($response, [2, 3])) // 200, 250, 354 etc.
							{ 
								$result = true; 
							} else { $error = 'Fail send data to '.$email.', '.$data; }
						} else { $error = 'Fail RCPT TO <'.$email.'>'.', '.$data; }
					} else { $error = 'Fail MAIL FROM <'.$sender.'>'.', '.$data; }
				} else { $error = 'Fail EHLO/HELO '.$this->localhost.', '.$data. ' '. $mxhosts[$id]. ':'. $conn_port; }

				fputs ($connection,"QUIT\r\n");
				fclose ($connection);

				if ($result){
					// echo 'OK ', $data, PHP_EOL;
					echo 'OK', PHP_EOL;
					return true;
				}
				if (!empty($error)) break;

				$id++;
			}
			if(!$mx_count) echo 'Error: mxhosts not found - ', $email, PHP_EOL;
			else if(!$connection) echo 'Timeout', PHP_EOL;
			else echo 'Error: ', $error, PHP_EOL;

			return false;
		}
		static function is_valid_email($email = "") { 
			return preg_match('~^[\w\d_\-\+\.]+@[\w\d\.\-_]+\.[\w\d\.\-_]+$~i', $email); 
		}

	}

	define('DIR_SITE', dirname(__FILE__));

	set_time_limit(0);

	$email = (!empty($argv[1])?$argv[1]:(!empty($_GET['email'])?$_GET['email']:''));
	if(empty($email)) {
		echo 'Error: email is empty', PHP_EOL;
		return;
	}
	CheckMail::self()->check_one($email));	
?>
