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

		$this->Regex = '~(' . implode( '|', $Regexes ) . ')~i';
	}

	public function GetMatchesForFileList( array $Files ) : array
	{
		$Matches = [];

		foreach( $Files as $Path )
		{
			$Actual = $this->GetMatchingRuleForFilePath( $Path );

			if( $Actual !== null )
			{
				if( isset( $Matches[ $Actual ] ) )
				{
					$Matches[ $Actual ]++;
				}
				else
				{
					$Matches[ $Actual ] = 1;
				}
			}
		}

		if( !empty( $Matches ) )
		{
			$EducatedGuess = $this->TryDeduceEngine( $Files, $Matches );

			if( $EducatedGuess !== null )
			{
				$Matches[ $EducatedGuess ] = 1;
			}
		}

		return $Matches;
	}

	public function TryDeduceEngine( array $Files, array $Matches ) : ?string
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
		// if( $NumFiles < 10 && $Matches["Evidence.HasEXE"] === 1 && $Matches["Evidence.HasPCK"] === 1 )
		// {
			// return "GameEngine.Godot";
		// }


		//.u files only turn up in idTech0 and UnrealEngine games -- if we haven't positively ID'd idTech0 so far, it's Unreal
		if(!empty($Matches["Evidence.U"]) && empty($Matches["Emulator.DOSBOX"])){
			return "GameEngine.Unreal";
		}

		//toc files only show up in Frostbite and UnrealEngine games -- if we haven't positively ID'd Unreal so far, it's Frostbite
		if(!empty($Matches["Evidence.TOC"])){
			return "GameEngine.Frostbite";
		}

		//If I have matched nothing else and I notice it has lowercase pck files, it's a pretty good guess that it is Godot
		if(!empty($Matches["Evidence.PCK"]))
		{
			$Executables = [];
			$LastFoundPck = null;

			foreach( $Files as $File )
			{
				if( basename( $File ) === 'data.pck' )
				{
					return "GameEngine.Godot";
				}

				$Extension = pathinfo( $File, PATHINFO_EXTENSION );

				if( $Extension === 'pck' )
				{
					$LastFoundPck = $File;
				}
				else if( $Extension === 'exe' )
				{
					$Executables[ $File ] = true;
				}
			}

			if( $LastFoundPck !== null )
			{
				$ExeName = substr( $LastFoundPck, 0, -3 ) . 'exe';

				if( isset( $Executables[ $ExeName ] ) )
				{
					return "GameEngine.Godot";
				}
			}
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
