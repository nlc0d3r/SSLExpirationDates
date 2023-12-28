<?php
	/**
	 * CertExp.php is a script that shows SSL certificate information for given domains.
	 * @author nlc0d3r
	 * @version v1.2
	 */

	/**
	 * Domain list
	 */
	$DomainList = [
		"notexistingdomain.xyz",
		"google.com",
		"facebook.com",
		"twitch.com",
		"",
	];

	/**
	 * Script configuration
	 */
	$Config = [
		// HTML teksts that can be edited/translated
		"HTML_TITLE"	=> "SSL Certs expiration dates",
		"HTML_DOMAIN"	=> "Domain",
		"HTML_ISSUER"	=> "Issuer",
		"HTML_DAYSLEFT"	=> "Days left",
		"HTML_STARTS"	=> "Starts",
		"HTML_ENDS"	=> "Expires",
		"HTML_OPEN"	=> "Open",
		// Debugging
		"DEBUG_MODE"	=> true,
		"DEBUG_DAYS"	=> 0, // Parametter to test alerts. Adds day offset if greater than 0. 
		// Alerts
		"ALERT_WARNING"	=> 10, // Days before SSL expire to show orange
		"ALERT_DANGER"	=> 2, // Days before SSL expire to show red

		"HTML_WARN_DAYSOFFSET"	=> "Day offset is set to: ",
		"HTML_WARN_UNREACHABLE"	=> "Domain is unreachable.",
	];

	/**
	 * @param CertExp $Config
	 * @param CertExp $DomainList
	 * @return CertExp $OUTPUT_LIST
	 */
	class CertExp
	{
		public $CFG 		= [];
		public $DOMAIN_LIST	= [];
		public $OUTPUT_LIST	= [];
		public $DATE;

		/**
		 * @param __construct $Config
		 * @param __construct $DomainList
		 */
		public function __construct( $Config, $DomainList )
		{
			$this->CFG = $Config;
			$this->debug( $this->CFG['DEBUG_MODE'] );

			date_default_timezone_set('GMT');
			$this->DATE = date( "d.m.Y H:i:s" );
			$this->testDay( $this->CFG['DEBUG_DAYS'] );

			$this->DOMAIN_LIST = $DomainList;
			$this->getCertData();
		}

		public function cURLConnector( $Domain, $mode )
		{
			$curl = curl_init();
			curl_setopt_array( $curl, [
				CURLOPT_URL		=> "https://". $Domain,
				CURLOPT_NOBODY		=> true,
				CURLOPT_VERBOSE		=> true,
				CURLOPT_CERTINFO	=> true,
				CURLOPT_AUTOREFERER	=> true,
				CURLOPT_FOLLOWLOCATION	=> true,
				CURLOPT_RETURNTRANSFER	=> true,
				CURLOPT_SSL_VERIFYPEER	=> false,
				CURLOPT_SSL_VERIFYHOST	=> false,
				CURLOPT_CONNECTTIMEOUT	=> 5,
				CURLOPT_MAXREDIRS	=> 1,
				CURLOPT_TIMEOUT		=> 5,
				CURLOPT_ENCODING	=> "",
				CURLOPT_USERAGENT	=> "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
			]);
			$result = curl_exec( $curl );
			$certInfo = curl_getinfo( $curl, CURLINFO_CERTINFO );
			$respCode = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
			curl_close($curl);

			switch ( $mode ) {
				case 'conn':
					return $respCode;
					break;
				case 'cert':
					return $certInfo;
					break;
			}
		}

		public function getCertData()
		{
			foreach ( $this->DOMAIN_LIST as $Key => $Domain ) {
				
				if ( $this->cURLConnector( $Domain, 'conn' ) !== 0 )
				{
					$certInfo = $this->cURLConnector( $Domain, 'cert' );
					
					// Parsing data
					$_CERT_STR = date( "d.m.Y H:i:s", strtotime( $certInfo[0]['Start date'] ) );
					$_CERT_EXP = date( "d.m.Y H:i:s", strtotime( $certInfo[0]['Expire date'] ) );
					$_CERT_LFT = floor( ( strtotime( $certInfo[0]['Expire date'] ) - strtotime( $this->DATE ) ) / 86400 );

					if( $_CERT_LFT <= $this->CFG['ALERT_DANGER'] ){
						$_COLOR = 'danger';
					} else if( $_CERT_LFT <= $this->CFG['ALERT_WARNING'] ){
						$_COLOR = 'warning';
					} else {
						$_COLOR = 'success';
					}

					preg_match( '/O\s\=\s([^,]+)/', $certInfo[0]['Issuer'], $match );
					$_CERT_ISR = trim( $match[1], ' ".' ?? '' );

					$this->OUTPUT_LIST[] = [
						"Domain"	=> $Domain,
						"Issuer"	=> $_CERT_ISR,
						"DaysLeft"	=> $_CERT_LFT,
						"Start"		=> $_CERT_STR,
						"End"		=> $_CERT_EXP,
						"State"		=> $_COLOR,
						"OnLine"	=> true,
					];
				} else {
					$this->OUTPUT_LIST[] = [
						"Domain"	=> $Domain,
						"Issuer"	=> false,
						"DaysLeft"	=> '1000000',
						"Start"		=> false,
						"End"		=> false,
						"State"		=> 'secondary',
						"OnLine"	=> false,
					];
				}
			}

			// Array sorting by Daysleft
			foreach ( $this->OUTPUT_LIST as $key => $value ) {
				$dl[$key] = $value['DaysLeft'];
			}
			$dl = array_column( $this->OUTPUT_LIST, 'DaysLeft');
			array_multisort($dl, SORT_ASC, $this->OUTPUT_LIST);
		}

		public function makeOutput()
		{
			$seq = 1;
			foreach ( $this->OUTPUT_LIST as $key => $item ) {
				echo '
					<tr class="'. $item['State'] .'">
						<td>'. $seq .'</td>
						<td>
							<a href="//'. $item['Domain'] .'" target="_blank" class="btn btn-dark btn-sm" title="'. $this->CFG['HTML_OPEN'] .'">
								<i class="bi bi-link"></i>
							</a>
							<span class="domain">'. $item['Domain'] .'</span>
						</td>
				';
				if ( $item['OnLine'] )
				{
					echo '
						<td>'. $item['Issuer'] .'</td>
						<td>'. $item['DaysLeft'] .'</td>
						<td>'. $item['Start'] .'</td>
						<td>'. $item['End'] .'</td>
					';
				} else {
					echo '
						<td colspan="4">
							<span class="text-danger"><i class="bi bi-ban"></i> '. $this->CFG['HTML_WARN_UNREACHABLE'] .'</span>
						</td>
					';	
				}
				echo '</tr>';
				$seq++;
			}
		}

		/**
		 * @param debug $mode
		 */
		public function debug( $mode = false )
		{
			if ( $mode )
			{
				ini_set('display_errors', 1);
				ini_set('display_startup_errors', 1);
				error_reporting(E_ALL);
			}
		}

		/**
		 * @param testDay $days
		 */
		public function testDay( $days )
		{
			if( $days > 0 )
			{
				$date = date( "d.m.Y H:i:s");
				$this->DATE = date( "d.m.Y H:i:s", strtotime( $date .' + '. $days .' day' ));
			}
		}

		public function dayOffsetWarning()
		{
			if ( $this->CFG['DEBUG_DAYS'] > 0 )
			{
				return $this->CFG['HTML_WARN_DAYSOFFSET'] . $this->CFG['DEBUG_DAYS'];
			}
		}
	}

	$C = new CertExp( $Config, $DomainList );

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?=$C->CFG['HTML_TITLE']?></title>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
		<style>
			.container-fluid { padding: 0;}
			.danger td { background-color: rgba(255, 0, 0, 0.4) }
			.warning td { background-color: rgba(255, 165, 0, 0.3) }
			.success td { background-color: rgb(25, 135, 84, 0.2) }
			.domain { margin: 0 0 0 10px; font-weight: bold; text-transform: uppercase; }
		</style>
	</head>
	<body>
		
		<div class="container-fluid">
			<div class="d-flex justify-content-between align-items-center">
				<div class="p-2"><h2><?=$C->CFG['HTML_TITLE']?></h2></div>
				<div class="p-2">
					<span<?php echo ( $C->CFG['DEBUG_DAYS'] > 0 ) ? ' class="text-danger"' : ' class="text-success"' ?>>
						<?=$C->DATE?><br>
						<?=$C->dayOffsetWarning()?>
					</span>
				</div>
			</div>
			<table class="table table-hover">
				<thead class="thead-dark">
					<tr>
						<th scope="col">#</th>
						<th scope="col"><?=$C->CFG['HTML_DOMAIN']?></th>
						<th scope="col"><?=$C->CFG['HTML_ISSUER']?></th>
						<th scope="col"><?=$C->CFG['HTML_DAYSLEFT']?></th>
						<th scope="col"><?=$C->CFG['HTML_STARTS']?> (<?=date( "T" )?>)</th>
						<th scope="col"><?=$C->CFG['HTML_ENDS']?> (<?=date( "T" )?>)</th>
					</tr>
				</thead>
				<tbody>
					<?php $C->makeOutput(); ?>
				</tbody>
			</table>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

	</body>
</html>
