-- Fix password hashes for the demo staff accounts.
USE employsmart;

UPDATE users SET password = '$2y$10$rkfjo3FQhgna0IP6RvkCWOFLZN0whnzk6dYNy2IwKKpDxCs7Q29Ay' WHERE email = 'admin@employsmart.com';
UPDATE users SET password = '$2y$10$Zzl/fWfahphZeT9VYbQkeeAjzPakrMUiIxDXAsY9Wd4swz8RSpHNi' WHERE email = 'peso@employsmart.com';
UPDATE users SET password = '$2y$10$gQRbkUeq5awxvNiJO1fFduSYKV2JYfd0Zwn0AJiUbbLnKSdugVboC' WHERE email = 'clcdo@employsmart.com';

SELECT 'Password hashes updated successfully!' AS status;