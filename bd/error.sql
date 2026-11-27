alter table usuarios
  add column if not exists telefono varchar(20) null default null,
  add column if not exists biografia text null default null,
  add column if not exists foto_perfil varchar(255) null default null;