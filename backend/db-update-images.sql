-- Update script to add image support to an existing rentalsystem database
USE rentalsystem;

ALTER TABLE vehicles
  ADD COLUMN image VARCHAR(255) DEFAULT NULL;

UPDATE vehicles SET image = 'images/placeholder.svg' WHERE image IS NULL;

UPDATE vehicles
SET image = CASE id
  WHEN 1 THEN 'images/toyota-corolla.svg'
  WHEN 2 THEN 'images/honda-civic.svg'
  WHEN 3 THEN 'images/ford-escape.svg'
  ELSE 'images/placeholder.svg'
END;
