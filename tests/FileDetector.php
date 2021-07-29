<?php
declare(strict_types=1);

class FileDetector
{
	public bool $FilterEvidenceMatches = true;
	public array $Map = [];
	private array $Regexes = [];

	public function __construct( string $Path )
	{
		$Rulesets = parse_ini_file( $Path, true, INI_SCANNER_RAW );

		if( empty( $Rulesets ) )
		{
			throw new \RuntimeException( 'rules.ini failed to parse' );
		}

		$MarkIndex = 0;

		foreach( $Rulesets as $Type => $Rules )
		{
			$Regexes = [];

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

			$this->Regexes[] = '~(' . implode( '|', $Regexes ) . ')~i';
		}
	}

	public function GetMatchesForFileList( array $Files ) : array
	{
		$Matches = [];

		foreach( $Files as $Path )
		{
			foreach( $this->Regexes as $Regex )
			{
				if( preg_match( $Regex, $Path, $RegexMatches ) )
				{
					$Match = $this->Map[ $RegexMatches[ 'MARK' ] ];

					if( isset( $Matches[ $Match ] ) )
					{
						$Matches[ $Match ]++;
					}
					else
					{
						$Matches[ $Match ] = 1;
					}
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

			if( $this->FilterEvidenceMatches )
			{
				$Matches = array_filter(
					$Matches,
					fn( string $Match ) : bool => !str_starts_with( $Match, 'Evidence.' ),
					ARRAY_FILTER_USE_KEY
				);
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

		if (!empty($Matches["Emulator.DOSBOX"])){
			//If it's a DOS game...

			if(!empty($Matches["Evidence.VSWAP"])){
				//If it's got VSWAP files it's probably idTech0 (Wolf3D engine)
				return "GameEngine.idTech0";
			}else if(!empty($Matches["Evidence.CFG"]) && !empty($Matches["Evidence.WAD"])){
				//If it's got CFG and WAD files it's probably idTech1 (DOOM engine)
				return "GameEngine.idTech1";
			}
		}

		//.u files only turn up in idTech0 and UnrealEngine games -- if we haven't positively ID'd idTech0 so far, it's Unreal
		if(!empty($Matches["Evidence.U"]) && empty($Matches["Emulator.DOSBOX"])){
			return "GameEngine.Unreal";
		}

		//.toc files only show up in Frostbite and UnrealEngine games -- if we haven't positively ID'd Unreal so far, it's Frostbite
		if(!empty($Matches["Evidence.TOC"])){
			return "GameEngine.Frostbite";
		}

		//Any 2 of options.ini + data.win + snd_<whatever>.ogg is a good sign of a GameMaker Game
		if( !empty($Matches["Evidence.OPTIONS_INI"]) + !empty($Matches["Evidence.DATA_WIN"]) + !empty($Matches["Evidence.SND_OGG"]) >= 2){
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

		//If I have matched nothing so far and I have a PK3 file, it's likely idTech3 (Quake3 engine)
		if(!empty($Matches["Evidence.PK3"])){
			return "GameEngine.idTech3";
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
