-- Assigne le rôle "Analyste financier" au compte déjà existant arahoulou@sitiame-capital.com
-- À exécuter une seule fois sur la base de production (psql / client SQL habituel).
--
-- is_platform_admin et is_accountant sont remis à false par précaution : le rôle
-- financial_analyst est exclusif des rôles admin plateforme / comptable cabinet
-- (même logique que pour les autres role_key gérés depuis Admin > RBAC).

UPDATE users
SET
    role_key = 'financial_analyst',
    is_platform_admin = false,
    is_accountant = false
WHERE email = 'arahoulou@sitiame-capital.com';

-- Vérification (doit renvoyer exactement 1 ligne avec role_key = financial_analyst) :
-- SELECT id, name, email, role_key, is_platform_admin, is_accountant
-- FROM users
-- WHERE email = 'arahoulou@sitiame-capital.com';
