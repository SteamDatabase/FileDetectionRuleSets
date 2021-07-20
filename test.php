<?php
declare(strict_types=1);

class FileDetector
{
	private array $Rulesets;

	public function __construct( $Path )
	{
		$Rulesets = parse_ini_file( $Path, true, INI_SCANNER_RAW );

		if( empty( $Rulesets ) )
		{
			throw new \RuntimeException( 'rules.ini failed to parse' );
		}

		$this->Rulesets = $Rulesets;
	}

	public function GetMatchingRuleForFilePath( string $Path ) : ?string
	{
		foreach( $this->Rulesets as $Type => $Rules )
		{
			foreach( $Rules as $Name => $Regex )
			{
				if( preg_match( $Regex, $Path ) )
				{
					return "$Type.$Name";
				}
			}
		}

		return null;
	}

	public function AssertMatch( string $Path, string $Expected ) : void
	{
		$Actual = $this->GetMatchingRuleForFilePath( $Path );

		if( $Actual !== $Expected )
		{
			throw new \RuntimeException( "Path \"$Path\" does not match \"$Expected\"" );
		}
	}

	public function AssertNull( string $Path ) : void
	{
		$Actual = $this->GetMatchingRuleForFilePath( $Path );

		if( $Actual !== null )
		{
			throw new \RuntimeException( "Path \"$Path\" returned \"$Actual\"" );
		}
	}
}

$Detector = new FileDetector( __DIR__ . '/rules.ini' );

// Positive tests
$Detector->AssertMatch( 'UnityPlayer.dll', 'GameEngine.Unity' );
$Detector->AssertMatch( 'UnityPlayer.so', 'GameEngine.Unity' );
$Detector->AssertMatch( 'UnityPlayer.dylib', 'GameEngine.Unity' );
$Detector->AssertMatch( 'Sub/Folder/UnityPlayer.dll', 'GameEngine.Unity' );
$Detector->AssertMatch( 'Sub/Folder/UnityPlayer.so', 'GameEngine.Unity' );
$Detector->AssertMatch( 'Sub/Folder/UnityPlayer.dylib', 'GameEngine.Unity' );

// Negative tests
$Detector->AssertNull( '' );
$Detector->AssertNull( '.' );
$Detector->AssertNull( '/' );
$Detector->AssertNull( ' ' );
$Detector->AssertNull( 'UnityPlayer.dlll' );
$Detector->AssertNull( 'unityplayer.dll' );
$Detector->AssertNull( '.UnityPlayer.dll' );
$Detector->AssertNull( 'UUnityPlayer.dll' );
