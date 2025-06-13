<?php
declare(strict_types=1);

class FileDetector
{
	private const string NO_EXTENSION_KEY = '_%any%_';

	public bool $FilterEvidenceMatches = true;

	/** @var string[] */
	public array $Map = [];

	/** @var array<string, string[]> */
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
			$Regexes = [];

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
					$HasSimpleExtension = preg_match( '/\\\.(?:(?<Extension>\w+)|\(\?:(?<MultiExtension>[\w\|]+)\))\$$/', $Regex, $SimpleExtension ) === 1;
					$HasCommonPrefix = false;

					// Regexes that match start of the file (root, or a folder) will be put into a separate regex
					if( str_starts_with( $Regex, $CommonFolderPrefix ) )
					{
						$HasCommonPrefix = true;
						$Regex = substr( $Regex, strlen( $CommonFolderPrefix ) ) . '(*:' . $MarkIndex . ')';
					}
					else
					{
						$Regex .= '(*:' . $MarkIndex . ')';
					}

					// Regexes that end with a file extension will be put into an array based on the extension
					// to reduce the amount of regexes needed to match for each file path
					if( $HasSimpleExtension )
					{
						// If regex ends with "\.dll$" then it's a single extension,
						// If regex ends with "\.(?:dylib|dll)$" then it's multi.
						$Extensions = empty( $SimpleExtension[ 'MultiExtension' ] ) ? [ $SimpleExtension[ 'Extension' ] ] : explode( '|', $SimpleExtension[ 'MultiExtension' ] );

						foreach( $Extensions as $Extension )
						{
							if( $HasCommonPrefix )
							{
								$Regexes[ $Extension ][ 0 ][] = $Regex;
							}
							else
							{
								$Regexes[ $Extension ][ 1 ][] = $Regex;
							}
						}
					}
					else if( $HasCommonPrefix )
					{
						$Regexes[ self::NO_EXTENSION_KEY ][ 0 ][] = $Regex;
					}
					else
					{
						$Regexes[ self::NO_EXTENSION_KEY ][ 1 ][] = $Regex;
					}

					$MarkIndex++;
				}
			}

			foreach( $Regexes as $Extension => $RegexesForExtension )
			{
				if( !empty( $RegexesForExtension[ 0 ] ) )
				{
					sort( $RegexesForExtension[ 0 ] );

					$this->Regexes[ $Extension ][] = '~' . $CommonFolderPrefix . '(?:' . implode( '|', $RegexesForExtension[ 0 ] ) . ')~i';
				}

				if( !empty( $RegexesForExtension[ 1 ] ) )
				{
					sort( $RegexesForExtension[ 1 ] );

					$this->Regexes[ $Extension ][] = '~' . implode( '|', $RegexesForExtension[ 1 ] ) . '~i';
				}
			}
		}

		ksort( $this->Regexes );
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
			foreach( $this->Regexes as $RegexesForExtension )
			{
				foreach( $RegexesForExtension as $Regex )
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
			$RegexesToTry = $this->Regexes[ self::NO_EXTENSION_KEY ] ?? [];
			$Extension = strtolower( pathinfo( $Path, PATHINFO_EXTENSION ) );

			if( isset( $this->Regexes[ $Extension ] ) )
			{
				$RegexesToTry = [ ...$this->Regexes[ $Extension ], ...$RegexesToTry ];
			}

			foreach( $RegexesToTry as $Regex )
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
			else if( $has( 'Evidence.VSWAP' ) || ( $has( 'Evidence.CFG' ) && $has( 'Evidence.WAD' ) ) )
			{
				//If it's got VSWAP files or CFG and WAD files it's probably idTech
				return 'Engine.idTech';
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
			if( $has( 'Evidence.RIM' ) || $has( 'Evidence.TGA' ) )
			{
				//RIM and TGA are found in Aurora but not in Infinity
				return 'Engine.Aurora';
			}

			return 'Engine.Infinity';
		}

		//Any 2 of options.ini + data.win + snd_<whatever>.ogg is a good sign of a GameMaker Game
		if( $count( [ 'Evidence.OPTIONS_INI', 'Evidence.DATA_WIN', 'Evidence.SND_OGG' ] ) > 1 )
		{
			return 'Engine.GameMaker';
		}

		//If it's got the Sierra interpreter and also .SCR files
		if( $has( 'Evidence.SIERRA_EXE' ) && $has( 'Evidence.SCR' ) )
		{
			return 'Engine.SCI';
		}

		//If I have a PCK file it might be Godot
		if( $has( 'Evidence.PCK' ) && self::IsEngineGodot( $Files ) )
		{
			return 'Engine.Godot';
		}

		//If I have matched nothing so far and I have a PK3 file, it's likely idTech3 (Quake3 engine)
		if( $has( 'Evidence.PK3' ) )
		{
			return 'Engine.idTech';
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

		$Pcks = [];
		$Exes = [];

		$ExecutableExtensions =
		[
			'EXE' => true, // Windows
			'X86' => true, // 32-bit Linux in Godot 2.x/3.x
			'X86_32' => true, // 32-bit Linux in Godot 4.x
			'X86_64' => true, // 64-bit Linux in all versions
		];

		foreach( $Files as $File )
		{
			$Extension = pathinfo( $File, PATHINFO_EXTENSION );
			$Extension = strtoupper( $Extension );

			if( $Extension === 'PCK' )
			{
				$Pcks[] = $File;
				continue;
			}

			// Mac and Linux can have extension-less executables
			if( empty( $Extension ) )
			{
				if( str_ends_with( dirname( $File ), '/MacOS' ) )
				{
					// Mac executable is not in the same folder, let's pretend they are in the same folder
					$File = str_replace( '/MacOS/', '/Resources/', $File );
				}

				$Exes[ $File . '.pck' ] = true;
			}
			else if( isset( $ExecutableExtensions[ $Extension ] ) )
			{
				// Strip extension from the file path
				$File = substr( $File, 0, -strlen( $Extension ) );

				$Exes[ $File . 'pck' ] = true;
			}
		}

		// This can happen if Evidence.PCK finds "BASE.PCK", but the $Pcks will be empty due to case sensitivity
		if( !empty( $Pcks ) )
		{
			$OnlyDataPck = true;

			//Otherwise we have to match up exe & pck pairs
			foreach( $Pcks as $Pck )
			{
				if( basename( $Pck ) !== 'data.pck' )
				{
					$OnlyDataPck = false;
				}

				//If we match an exe and a pck file pair, we're good
				if( isset( $Exes[ $Pck ] ) )
				{
					return true;
				}
			}

			if( $OnlyDataPck )
			{
				return true;
			}
		}

		return false;
	}
}
