<?php
declare(strict_types=1);

class FileDetector
{
	public bool $FilterEvidenceMatches = true;
	public array $Map = [];
	public array $Regexes = [];

	public function __construct( ?array $Rulesets, ?string $Path )
	{
		$Rulesets ??= parse_ini_file( $Path, true, INI_SCANNER_RAW );

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
					$this->Map[ $MarkIndex ] = "$Type.$Name";

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

	public function GetMatchedFiles( array $Files ) : array
	{
		$Matches = [];

		foreach( $Files as $Path )
		{
			foreach( $this->Regexes as $Regex )
			{
				if( preg_match( $Regex, $Path, $RegexMatches ) )
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
		// helper functions
		$has = fn( string $Match ) : bool => isset( $Matches[ $Match ] );
		$not = fn( string $Match ) : bool => !isset( $Matches[ $Match ] );
		$count = function( array $Search ) use ( $Matches ) : int
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

		//.toc, .sb, and .cas files are associated with Frostbite  -- if we haven't positively ID'd anything else so far, and we have 2 of these we guess Frostbite
		if( $count( [ 'Evidence.TOC', 'Evidence.SB', 'Evidence.CAS' ] ) > 1 )
		{
			return 'Engine.Frostbite';
		}

		//If we have both BIF and TLK files it's probably Aurora Engine
		if( $count( ['Evidence.BIF', 'Evidence.TLK']) > 1)
		{
			return 'Engine.Aurora';
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

		//If I have PCK files it might be Godot
		if( $has( 'Evidence.PCK' ) )
		{
			$Pcks = [];
			$LastFoundExe = '';

			foreach( $Files as $File )
			{
				//a data.pck file is usually a dead giveaway of Godot
				if( basename( $File ) === 'data.pck' )
				{
					return 'Engine.Godot';
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
			if( $LastFoundExe !== '' )
			{
				$PckName = substr( $LastFoundExe, 0, -3 ) . 'pck';

				if( isset( $Pcks[ $PckName ] ) )
				{
					return 'Engine.Godot';
				}
			}
		}

		//If I have a package.nw file and it matches nodeJS, it's probably Construct
		if( $has( 'Evidence.PACKAGE_NW' ) && $has( 'SDK.NodeJS' ) )
		{
			return 'Engine.Construct';
		}

		//If I have matched nothing so far and I have a PK3 file, it's likely idTech3 (Quake3 engine)
		if( $has( 'Evidence.PK3' ) )
		{
			return 'Engine.idTech3';
		}

		return null;
	}
}
