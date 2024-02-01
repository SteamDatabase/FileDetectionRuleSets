<?php
declare(strict_types=1);

class FileDetector
{
	public bool $FilterEvidenceMatches = true;

	/** @var string[] */
	public array $Map = [];

	/** @var string[] */
	public array $Regexes = [];

	/**
	 * @param ?array<string, array<string, string|string[]>> $Rulesets
	 */
	public function __construct( ?array $Rulesets, ?string $Path )
	{
		if( $Rulesets === null )
		{
			if( $Path === null )
			{
				throw new RuntimeException( 'Pass in rulesets or path.' );
			}

			/** @var array<string, array<string, string|string[]>> $Rulesets */
			$Rulesets = parse_ini_file( $Path, true, INI_SCANNER_RAW );
		}

		// This is a common regex to detect folders (or files in root folder),
		// as there are enough of these rules, we combine these into a subregex
		$CommonFolderPrefix = '(?:^|/)';
		$MarkIndex = 0;

		foreach( $Rulesets as $Type => $Rules )
		{
			$Regexes =
			[
				0 => [],
				1 => [],
			];

			foreach( $Rules as $Name => $RuleRegexes )
			{
				if( !is_array( $RuleRegexes ) )
				{
					$RuleRegexes = [ $RuleRegexes ];
				}

				foreach( $RuleRegexes as $Regex )
				{
					$this->Map[ $MarkIndex ] = "{$Type}.{$Name}";

					$Regex = strtolower( $Regex );

					if( str_starts_with( $Regex, $CommonFolderPrefix ) )
					{
						$Regexes[ 0 ][] = substr( $Regex, strlen( $CommonFolderPrefix ) ) . '(*:' . $MarkIndex . ')';
					}
					else
					{
						$Regexes[ 1 ][] = $Regex . '(*:' . $MarkIndex . ')';
					}

					$MarkIndex++;
				}
			}

			if( !empty( $Regexes[ 0 ] ) )
			{
				sort( $Regexes[ 0 ] );
				$this->Regexes[] = '~' . $CommonFolderPrefix . '(?:' . implode( '|', $Regexes[ 0 ] ) . ')~i';
			}

			if( !empty( $Regexes[ 1 ] ) )
			{
				sort( $Regexes[ 1 ] );

				$this->Regexes[] = '~' . implode( '|', $Regexes[ 1 ] ) . '~i';
			}
		}
	}

	/**
	 * @param string[] $Files
	 *
	 * @return array<array{File: string, Match: string}>
	 */
	public function GetMatchedFiles( array $Files ) : array
	{
		$Matches = [];

		foreach( $Files as $Path )
		{
			foreach( $this->Regexes as $Regex )
			{
				if( preg_match( $Regex, $Path, $RegexMatches ) === 1 )
				{
					$Match = $this->Map[ $RegexMatches[ 'MARK' ] ];

					$Matches[] =
					[
						'File' => $Path,
						'Match' => $Match,
					];
				}
			}
		}

		return $Matches;
	}

	/**
	 * @param string[] $Files
	 *
	 * @return array<string, int>
	 */
	public function GetMatchesForFileList( array $Files ) : array
	{
		$Matches = [];

		foreach( $Files as $Path )
		{
			foreach( $this->Regexes as $Regex )
			{
				if( preg_match( $Regex, $Path, $RegexMatches ) === 1 )
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
			$EducatedGuess = self::TryDeduceEngine( $Files, $Matches );

			if( $EducatedGuess !== null )
			{
				$Matches[ $EducatedGuess ] = 1;
			}

			if( $this->FilterEvidenceMatches )
			{
				$Matches = array_filter(
					$Matches,
					static fn( string $Match ) : bool => !str_starts_with( $Match, 'Evidence.' ),
					ARRAY_FILTER_USE_KEY
				);
			}
		}

		return $Matches;
	}

	/**
	 * @param string[] $Files
	 * @param array<string, int> $Matches
	 */
	private static function TryDeduceEngine( array $Files, array $Matches ) : ?string
	{
		// helper functions
		$has = static fn( string $Match ) : bool => isset( $Matches[ $Match ] );
		$not = static fn( string $Match ) : bool => !isset( $Matches[ $Match ] );

		$count = static function( array $Search ) use ( $Matches ) : int
		{
			$Count = 0;

			foreach( $Search as $Match )
			{
				if( isset( $Matches[ $Match ] ) )
				{
					$Count++;
				}
			}

			return $Count;
		};

		if( $has( 'Evidence.ARC' ) && $has( 'Evidence.TAB' ) )
		{
			return 'Engine.ApexEngine';
		}

		if( $has( 'Evidence.RPF' ) && $has( 'Evidence.METADATA_DAT' ) )
		{
			return 'Engine.RAGE';
		}

		if( $has( 'Evidence.HDLL' ) && $not( 'Engine.Lime_OR_OpenFL' ) )
		{
			return 'Engine.Heaps';
		}

		if( $has( 'Emulator.DOSBOX' ) )
		{
			//If it's a DOS game...

			if( $has( 'Evidence.Build' ) )
			{
				//If it matches the pattern of a Build engine game (Duke Nukem 3D engine)
				return 'Engine.Build';
			}
			else if( $has( 'Evidence.VSWAP' ) )
			{
				//If it's got VSWAP files it's probably idTech0 (Wolf3D engine)
				return 'Engine.idTech0';
			}
			else if( $has( 'Evidence.CFG' ) && $has( 'Evidence.WAD' ) )
			{
				//If it's got CFG and WAD files it's probably idTech1 (DOOM engine)
				return 'Engine.idTech1';
			}
		}

		//.u files only turn up in idTech0 and UnrealEngine games -- if we haven't positively ID'd idTech0 so far, it's Unreal
		if( $has( 'Evidence.U' ) && $not( 'Emulator.DOSBOX' ) )
		{
			return 'Engine.Unreal';
		}

		//If we have both BIF and TLK files it's probably a BioWare Engine
		if( $count( [ 'Evidence.BIF', 'Evidence.TLK' ] ) > 1 )
		{
			if( $has('Evidence.RIM') || $has('Evidence.TGA') )
			{
				//RIM and TGA are found in Aurora but not in Infinity
				return 'Engine.Aurora';
			}
			return 'Engine.Infinity';
		}

		//Any 2 of options.ini + data.win + snd_<whatever>.ogg is a good sign of a GameMaker Game
		if( $count( [ 'Evidence.OPTIONS_INI', 'Evidence.DATA_WIN', 'Evidence.SND_OGG' ] ) > 1)
		{
			return 'Engine.GameMaker';
		}

		//If it's got the Sierra interpreter and also .SCR files
		if( $has( 'Evidence.SIERRA_EXE' ) && $has( 'Evidence.SCR' ) )
		{
			return 'Engine.SCI';
		}

		//If I have a PCK file it might be Godot
		if( $has( 'Evidence.PCK' ) && $count(['Engine.Unreal', 'Engine.idTech5', 'Engine.idTech6', 'Engine.idTech7', 'Emulator.DOSBOX']) == 0 && self::IsEngineGodot( $Files ) )
		{
			return 'Engine.Godot';
		}

		//If I have matched nothing so far and I have a PK3 file, it's likely idTech3 (Quake3 engine)
		if( $has( 'Evidence.PK3' ) )
		{
			return 'Engine.idTech3';
		}

		return null;
	}

	/**
	 * @param string[] $Files
	 */
	private static function IsEngineGodot( array $Files ) : bool
	{
		//This is a really long and annoying check. Basically we have two things to look for:
		//1. A single .pck file named exactly "data.pck", and NO other pck files
		//2. For every executable, a correspondingly named pck file, and no other pck files

		$swapExtension = static fn( string $FileName, string $OldExtension, string $NewExtension ) : string => basename( $FileName, $OldExtension ) . $NewExtension;
		$Pcks = [];
		$Exes = [];

		foreach( $Files as $File )
		{
			$Extension = strtolower( pathinfo( $File, PATHINFO_EXTENSION ) );
			$BaseFile = basename( $File );

			if( $Extension === 'pck' )
			{
				$Pcks[ $BaseFile ] = true;
			}
			if( $Extension === 'exe' )
			{
				$Exes[ $swapExtension( $BaseFile, ".exe", ".pck" ) ] = true;
			}
			else if( $Extension === 'x86' ) // 32-bit Linux in Godot 2.x/3.x
			{
				$Exes[ $swapExtension( $BaseFile, ".x86", ".pck" ) ] = true;
			}
			else if( $Extension === 'x86_32' ) // 32-bit Linux in Godot 4.x
			{
				$Exes[ $swapExtension( $BaseFile, ".x86_32", ".pck" ) ] = true;
			}
			else if( $Extension === 'x86_64' ) // 64-bit Linux in all versions
			{
				$Exes[ $swapExtension( $BaseFile, ".x86_64", ".pck" ) ] = true;
			}
			else
			{
				//Mac and Linux can have extensionless executables, make sure we don't have folders in the mix, just filenames though
				$Exes[ $BaseFile . ".pck" ] = true;
			}
		}

		// This can happen if Evidence.PCK finds "BASE.PCK", but the $Pcks will be empty due to case sensitivity
		if( !empty( $Pcks ) )
		{
			//If we have exactly 1 PCK file and it is data.pck, we can skip all the fancy checks
			if( count( $Pcks ) === 1 && array_key_first( $Pcks ) === 'data.pck' )
			{
				return true;
			}

			//Otherwise we have to match up exe & pck pairs
			foreach ( array_keys( $Pcks ) as $pck )
			{
				//If we match an exe and a pck file pair, we're good
				if( isset( $Exes[ $pck ] ) )
				{
					return true;
				}
			}
		}

		return false;
	}
}
