-- Extra base units for ingredients: only units at or below g/ml in size, or discrete counts (no kg, l, lb...).
ALTER TABLE ingredients MODIFY unit ENUM('g','ml','u','mg','gota','pizca','rebanada','diente','hoja') NOT NULL DEFAULT 'g';
