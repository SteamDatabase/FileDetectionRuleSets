## SteamDB File Detection Rule Sets

This is a set of scripts that are used by [SteamDB](https://steamdb.info) to make educated guesses about the engine(s) & technology used to build various games.

It makes educated guesses, but they are just that, guesses.  
It is not perfect.  
It will never be perfect.  
Do not expect it to be perfect.

You can browse the result of this here: https://steamdb.info/tech/

## Overview

Every app on Steam is associated with a number of file depots. For each app on Steam, SteamDB will run these scripts over all the filenames in all of its depots.
Note that it is file**names**, not files. These scripts only scan the names of the files, not the data they contain. Note that there are over 100 million files on SteamDB, so scanning filenames alone is already a pretty big task.

These detections rely on SteamDB being able to access the file lists. [Use SteamDB's token dumper program if you can to improve the coverage!](https://steamdb.info/tokendumper/)

## Pattern-matching rules

[`rules.ini`](rules.ini) defines a set of regular expressions which are run against every filename stored on SteamDB. A PHP script uses the resulting matches to make educated guesses about what the most likely technology could be.

The ini file defines multiple sections, each with its own sub-patterns:

- Engine
- Evidence
- Container
- Emulator
- AntiCheat
- SDK

Here's an example of some rule patterns:

```
[Engine]
AdobeAIR = (?:^|/)Adobe AIR(?:$|/)
AdventureGameStudio = (?:^|/)(?:AGSteam\.dll|acsetup\.cfg)$
XNA[] = (?:^|/|\.)XNA(?:$|/|\.)
XNA[] = (?:^|/)xnafx31_redist\.msi$
FNA = (?:^|/)fna\.dll$
```

This snippet defines a section named "Engine." On SteamDB pattern names are prefixed with the section name. So that becomes "Engine.AdobeAIR", "Engine.AdventureGameStudio", "Engine.XNA", and so on.

Let's look at that first line which mentions AdobeAIR: `AdobeAIR = (?:^|/)Adobe AIR(?:$|/)`.
This regular expression assigns the pattern value of `Engine.AdobeAIR` to any file or directory that has the exact name "Adobe AIR", or contains that exact phrase as a parent directory in its path. We highly recommend the site [regexr.com](https://regexr.com) to test your regular expressions.

Some things to note:

- All rules are case INsensitive
- You can assign multiple rules to a single definition, and any of them will cause a match. Just use the `[]` after the pattern name as shown in the above example for XNA.
- The regex pattern runs on the full file path as it appears in the depot, e.g. `game/bin/win64/dota2.exe`, not just `dota2.exe`
- File paths will always use `/` (forward slash) as the path separator
- The regex generator uses `~` as the boundary, so there is no need to escape `/`
- Detections that work only for a single game are generally unwanted

**Engine** means game/software engines. The definition for this is pretty fuzzy and invites endless debate but basically if it is a big library or toolkit that many people use to make games and software we call that an engine.

**Evidence** are patterns that get fed to the script on a second pass to help identify things that weren't identified on the first pass.

**Container** is a category to refer to things like Electron, a common wrapper for HTML5 games. This is because games that use Electron often have some other technology they're using that they would consider their actual engine, such as PixiJS, Phaser, OpenFL, Heaps, etc.

**Emulator** is for identifying packed-in emulation technology. For instance, many DOS games come packaged with DOSBOX, and so we note those files with `Emulator.DOSBOX`.

**AntiCheat** is for anti-cheat files like BattlEye, EasyAntiCheat, and PunkBuster.

**SDK** are all other libraries and software development kits that an app might be using.

## How it works

A two-pass script runs over every file. On the first pass it tries to make a "slam dunk" identification based on a strong signal from any file. **Engine** patterns are primarily used here, looking for obvious things like Unity, Unreal, MonoGame, RPGMaker, XNA/FNA, AdobeAIR, etc. These game engines often have very clear signatures — ie "UnityEngine.dll". An "Engine" pattern should be strong enough to confidently match against a particular engine based on _one_ single positive match against any file in the depot.

**Evidence** patterns are meant for building up "hints" about what kind of engine or technology might be in use when a slam-dunk identification is not possible from a single pattern match. Once all the obvious tests have been made, if a particular app has no clear identification it will do a second pass in [`FileDetector.php`](tests/FileDetector.php) by calling the `TryDeduceEngine()` function. For instance, GameMaker games are hard to identify based on any single file, but they have a common pattern: an "options.ini" file, a "data.win" file, and an audio file matching the pattern `snd_<something>.ogg`. The problem is that these are pretty generic filenames that often occur outside of GameMaker games. However, once we have already ruled out most of the other engines from our first logic pass if we find two or more of these three file patterns chances are very good we're looking at a GameMaker game.

## Tests

If you have PHP installed locally, you can run the tests from the root directory by typing `php tests/Test.php`.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) file.

## How SteamDB uses this information

SteamDB makes two sets of identifications — the technology the *file* likely represents, and the technology the *app* makes use of. Each file will match against at most one rule in a section. Therefore the order of the rules and the two-pass tests matters, but an app can have multiple rulings applied to it.

It is even possible for an app to have multiple game engines — this happens when an app represents a multi-game compilation, or uses one technology for its launcher app and one for the game itself, or whatever.

## False negatives and positives

[Report false classifications here](https://github.com/SteamDatabase/FileDetectionRuleSets/issues/new/choose)

It is inevitable when working at this scale that we will have both false negatives and false positives.

False negatives are when a game is made with a certain technology and we fail to identify it as such. For many engines, there is nothing we can do about this because there simply isn't enough information left by the filenames alone to reliably detect them. Some engines leave no reliable trace of their identity whatsoever (particularly HTML5 game engines), others like GameMaker and Godot leave subtle patterns that allow us to make educated guesses, and others are super obvious.

We try to err on the side of avoiding false positives, even if that causes us to have more false negatives.

That said, this is all fuzzy at best and all we can do is try to select for the best tradeoffs. Don't expect this tool to be an omniscient oracle, it's operations are quite simple.

## Absence of evidence is not evidence of absence

Something to emphasize is we are certainly undercounting engines/technology in three main ways:

1. We can't detect things we don't yet have rules for
2. False negatives will keep us from detecting every instance of a particular technology, even if we have rules for it
3. Some engines are fundamentally undetectable or extremely difficult to identify

For instance, the [HaxeFlixel](haxeflixel.com/) game engine likely represents a large percentage of Lime/OpenFL games on Steam. However, HaxeFlixel does not leave a particular signature that easily distinguishes games made with it from conventional Lime/OpenFL games. We can't even distinguish between games made in Lime (a low level framework) and games made in OpenFL (a high level framework based on Lime). This pattern likely repeats with many other game engines and technologies.

So please note these limitations any time you use this data for anything important.

## One game can match multiple engines

Sometimes games will include extra software like a level editor, a launcher, or configuration tool and that will be written in an engine other than what was used to build the game itself. Therefore it is not uncommon to find games that match multiple engines. The script is not able to disentangle this information, so an appid matching for multiple engines is simply saying that it found files matching the signatures of all of those engines within the depots associated with that appid. Nothing more, nothing less.

For a practical example: *The Witcher: Enhanced Edition* contains a digital comic book that is displayed in a standalone app created with the Unity Engine. The game itself was written in the Aurora engine.
