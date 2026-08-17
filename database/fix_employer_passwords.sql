-- Fix employer passwords
UPDATE users SET password = '$2y$10$RmUSaUxh4xfF5TPqAvFOu.7QRH5yz3xJRzX9oR.Z/mrO/HMMEWNfi' WHERE role = 'employer';
