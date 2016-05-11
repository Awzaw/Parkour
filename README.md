TO INSTALL: Place the .phar in your plugins folder... restart... make sure you have the correct permissions (see below) if you use a permissions manager, or make signs as OP.

First make a START sign with the following lines:

Line1: pk
Line2: start
Line3: NAME OF PARKOUR
Line4: ID:AMOUNT (the reward)

Then make a FINISH sign

Line1: pk
Line2: finish
Line3: NAME OF PARKOUR
Line4: (Optional Comment)


The #reward line at the end of the start sign can be Wood:5 or 17:5 or just Wood or 5 in which case it defaults to 64 items. If the reward line is blank, it will default to 64 Diamonds. If you use block names instead of ID's, please make sure you spell them correctly.

The last line of the Finish sign is optional, and can be used to say who made the parkour (also displayed when players start a parkour), or a comment.

If not already playing parkour, clicking a Parkour Finish sign teleports the player to the parkour start sign.

Permissions (all default to OP)
parkour - players can build and destroy any parkour
parkour.create - players can build parkour and only destroy their own

Both Start and Finish signs can be broken and remade - they will be automatically linked to an existing Start/Finish sign of the same name. When either sign is destroyed the Top Score is also reset.

Any cheating that involves changing gamemode or teleporting should be disabled
