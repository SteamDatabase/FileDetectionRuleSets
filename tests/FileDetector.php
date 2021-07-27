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

		//.u files only turn up in idTech0 and UnrealEngine games -- if we haven't positively ID'd idTech0 so far, it's Unreal
		if(!empty($Matches["Evidence.U"]) && empty($Matches["Emulator.DOSBOX"])){
			return "GameEngine.Unreal";
		}

		//.toc files only show up in Frostbite and UnrealEngine games -- if we haven't positively ID'd Unreal so far, it's Frostbite
		if(!empty($Matches["Evidence.TOC"])){
			return "GameEngine.Frostbite";
		}
		
		//options.ini + data.win is a good sign of a GameMaker Game
		if(!empty($Matches["Evidence.OPTIONS_INI"]) && !empty($Matches["Evidence.DATA_WIN"])){
			return "GameEngine.GameMaker";
		}
		
		//If it's got the Sierra interpreter and also .SCR files
		if (!empty($Matches["Evidence.SIERRA_EXE"]) && !empty($Matches["Evidence.SCR"])){
			return "GameEngine.SCI";
		}
		
		//If I have PCK files it might be Godot
		if(!empty($Matches["Evidence.PCK"]))
		{
			$Pcks = [];
			$LastFoundExe = "";

			foreach( $Files as $File )
			{
				//a data.pck file is usually a dead giveaway of Godot
				if( basename( $File ) === 'data.pck' )
				{
					return "GameEngine.Godot";
				}

				$Extension = pathinfo( $File, PATHINFO_EXTENSION );

				if( $Extension === 'exe' )
				{
					$LastFoundExe = $File;
				}
				else if( $Extension === 'pck' )
				{
					$Pcks[ $File ] = true;
				}
			}
			
			//If I have a matching EXE and PCK pair it's almost certainly GODOT
			if( $LastFoundExe !== "" )
			{
				$PckName = substr( $LastFoundExe, 0, -3 ) . 'pck';
				
				if( isset( $Pcks[ $PckName ] ) )
				{
					return "GameEngine.Godot";
				}
			}
		}
		
		//If I have a package.nw file and it matches nodeJS, it's probably Construct
		if(!empty($Matches["Evidence.PACKAGE_NW"]) && !empty($Matches["SDK.NodeJS"])){
			return "GameEngine.Construct";
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
