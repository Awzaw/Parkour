![Logo](http://jdcraft.net/img/obstacle250.png)

VERSION 1.1.0

TO INSTALL: Place the .phar in your plugins folder... restart... make sure you have the correct permissions (see below) if you use a permissions manager, or make signs as OP.

First make a START sign with the following lines:

Line1: pk

Line2: start

Line3: NAME OF PARKOUR

Line4: ID:AMOUNT (or AMOUNT$)

Then make a FINISH sign

Line1: pk

Line2: finish

Line3: NAME OF PARKOUR

The #reward line at the end of the start sign can be Wood:5 or 17:5 or just Wood or 5 in which case it defaults to 64 items. If the reward line is blank, it will default to 64 Diamonds. If you use block names instead of ID's, please make sure you spell them correctly, and please note that some names and ID's don't work as expected.
To give 2 diamond swords(276), for example, you must type: Diamond Sword:2, (two words), not 276 or "DiamondSword"... for a diamond block it is "DiamondBlock", however.

For money rewards you must use EconomyAPI and add `1000$` on the reward line, for example.

The last line of the Finish sign is optional, and can be used to say who made the parkour (also displayed when players start a parkour), or a comment.
If not already playing parkour, clicking a Parkour Finish sign teleports the player to the parkour start sign.

COMMANDS:

/pk - displays the help for signs and commands

/pk list - displays a list of all parkours, green names are valid parkour with a start and stop, red names are parkour start signs with no finish sign, grey names are the makers

/pk {name of parkour} - teleports the player to the start for the parkour

/pk go - teleports the player to a random parkour with a start and finish sign.

/pk killbrick {name of parkour} {ID} - set the killbrick to ID (tp players back to parkour Start)

/pk killbrick {name of parkour} none/no - No killbrick


Permissions (all default to OP)

parkour - players can build and destroy any parkour

parkour.create - players can build parkour and only destroy their own

Both Start and Finish signs can be broken and remade - they will be automatically linked to an existing Start/Finish sign of the same name. When either sign is destroyed the Top Score is also reset.

Any problems, please let me know in the discussion thread, not the reviews.

Any cheating that involves changing gamemode or teleporting is automatically disabled

