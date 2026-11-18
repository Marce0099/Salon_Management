-- Exclusive groups for order modifiers: modifiers sharing the same exclusive_group (case-insensitive) cannot be picked
-- together on one order line (e.g. group "Término": Término medio / Tres cuartos / Bien cocido). NULL = no group.
ALTER TABLE modifiers ADD COLUMN exclusive_group VARCHAR(40) NULL DEFAULT NULL AFTER kitchen_text;
