<?php
declare(strict_types=1);

class FileDetector
{
	public array $Map = [];
	private string $Regex;

	public function __construct( string $Path )
	{
		$Rulesets = parse_ini_file( $Path, true, INI_SCANNER_RAW );

		if( empty( $Rulesets ) )
		{
			throw new \RuntimeException( 'rules.ini failed to parse' );
		}

		$Regexes = [];
		$MarkIndex = 0;

		foreach( $Rulesets as $Type => $Rules )
		{
			foreach( $Rules as $Name => $Regex )
			{
				if( is_array( $Regex ) )
				{
					$Regex = '(?:' . implode( '|', $Regex ) . ')';
				}

				if( self::RegexHasCapturingGroups( $Regex ) )
				{
					throw new \Exception( "$Type.$Name: Regex \"$Regex\" contains a capturing group" );
				}

				$Regexes[] = $Regex . '(*MARK:' . $MarkIndex . ')';
				$this->Map[ $MarkIndex ] = "$Type.$Name";

				$MarkIndex++;
			}
		}

		$this->Regex = '~(' . implode( '|', $Regexes ) . ')~';
	}

	public function DetermineMatchFromEvidence( int $NumFiles, array $Matches ) : ?string
	{
		/*
		This function is ONLY run if a one-shot regex test fails to conclusively match the depot
		It will try to guess what the file is based on "Evidence.*" patterns and the number of files 
		in the depot. It's not perfect but will give us more power than one-shot matches alone.
		*/
		
		//Rather than cramming this logic in data with some ad-hoc format it seems more maintainable to express these checks directly as code:
		
		//GODOT:
		//The typical signature for a Godot-engine game is "low file count, does not match any other engine
		//yet, and has a single .exe file as well as a single .pck file." If the developer splits these files
		//across depots the test will fail, so maybe we should just check for low file count + 1 pck file
		if($NumFiles < 10 && $Matches["Evidence.HasEXE"] == 1 && $Matches["Evidence.HasPCK"] == 1){
			return "GameEngine.Godot";
		}
		
		//SOME OTHER ENGINE:
		if(false){
			return "GameEngine.Whatever";
		}
		
		return null;
	}
	
	public function GetMatchingRuleForFilePath( string $Path ) : ?string
	{
		if( preg_match( $this->Regex, $Path, $Matches ) )
		{
			return $this->Map[ $Matches[ 'MARK' ] ];
		}

		return null;
	}

	private static function RegexHasCapturingGroups( string $regex ) : bool
	{
		// From https://github.com/nikic/FastRoute/blob/dafa1911fd7c1560c64d19556cbd4c599fed15ea/src/DataGenerator/RegexBasedAbstract.php#L181
		if( strpos( $regex, '(' ) === false )
		{
			// Needs to have at least a ( to contain a capturing group
			return false;
		}

		// Semi-accurate detection for capturing groups
		return (bool)preg_match(
			'~
				(?:
					\(\?\(
				  | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
				  | \\\\ .
				) (*SKIP)(*FAIL) |
				\(
				(?!
					\? (?! <(?![!=]) | P< | \' )
				  | \*
				)
			~x',
			$regex
		);
	}
}
