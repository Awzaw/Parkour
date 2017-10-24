### General

Parkour is an easy-to-use plugin for creating parkour complete with best times, cash/item rewards, anti-cheat, kill bricks and more.

### Create a Parkour

First make a START sign with the following lines:

Line1: `pk`

Line2: `start`

Line3: `<your parkour name>`

Line4: `ID:AMOUNT` (or `AMOUNT$`)

Then make a FINISH sign

Line1: `pk`

Line2: `finish`

Line3: `<your parkour name>`

The #reward line at the end of the start sign can be Wood:5 or 17:5 or just Wood or 5 in which case it defaults to 64 items. If the reward line is blank, it will default to 64 Diamonds.

If you use block names instead of ID's, please make sure you spell them correctly, and please note that some names and ID's don't work as expected.

To give 2 diamond swords, for example, you must type: `Diamond Sword:2`, (two words), not `DiamondSword`... But for a diamond block it is `DiamondBlock`.

For money rewards you must use EconomyAPI and type `1000$` on the reward line, for example.

The last line of the Finish sign is optional, and can be used to say who made the parkour (also displayed when players start a parkour), or a comment.

### Commands

`/pk` - displays the help for signs and commands

`/pk list` - displays a list of all parkours, green names are valid parkour with a start and stop, red names are parkour start signs with no finish sign, grey names are the makers

`/pk <name of parkour>` - teleports the player to the start for the parkour

`/pk go` - teleports the player to a random working parkour.

`/pk killbrick <name of parkour> <ID>` - set the kill-brick to ID (teleports players back to the parkour Start sign)

`/pk killbrick <name of parkour> <none|no>` - Do not use a kill-brick


### Permissions
All permissions default to OP.

`parkour` - players can build and destroy any parkour

`parkour.create` - players can build parkour and only destroy their own

### FAQ
If not already playing parkour, clicking a Parkour Finish sign teleports the player to the parkour start sign.

Both Start and Finish signs can be broken and remade - they will be automatically linked to an existing Start/Finish sign of the same name.

When either sign is destroyed the Top Score is also reset.

Any cheating that involves changing gamemode or teleporting is automatically disabled

