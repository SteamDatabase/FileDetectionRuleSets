# Known un-detected or chronically under-counted technologies

## The following engines are known to be underreported by our heuristics

### HaxeFlixel

HaxeFlixel is one of the most popular engines built on Lime/OpenFL but gives off no particular signal of its own. The best we can do is detect Lime/OpenFL.

### Clickteam Fusion

Many Clickteam Fusion games are packaged as standalone executables with no supporting files.

### Construct

We detect Construct games based on the presence of a particular JS file. However, it is possible to package all the content in a package.nw file, which hides this information from us and makes it indistinguishable from a generic Node.JS game. The best we can do for these missing cases is detect NodeJS.

### GameMaker

It is possible to package a GameMaker game as a standalone executable with no supporting files, or only very generic ones.

### Godot

Godot games can also be packaged as standalone executables with no external asset files.

### Stencyl

Stencyl games don't always have recognizable files so it may not give any signal on it's own.

## The following SDKs are known to be undetected by our heurestics

### Havok

Games using Havok SDK Suite (Physics, Cloth and AI) are usually don't have any special files for them, so we can not detect them.

## The following AntiCheats are known to be undetected by our heretics

### Valve Anti-Cheat

It's built into Steam and game exe, so its can't be detected

### Denuvo Anti-Cheat

Denuvo is built into exe, so it can not be detected either
