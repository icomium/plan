CREATE TABLE units (
  id INTEGER PRIMARY KEY,
  uuid TEXT NOT NULL UNIQUE DEFAULT (lower(hex(randomblob(16)))),
  kind TEXT NOT NULL CHECK (kind IN ('directorate','department')),
  name TEXT NOT NULL CHECK (length(trim(name)) > 0),
  name_key TEXT NOT NULL,
  head_employee_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
  sort INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX one_directorate ON units(kind) WHERE kind = 'directorate';
CREATE UNIQUE INDEX uq_unit_name ON units(name_key) WHERE is_active = 1;

CREATE TABLE positions (
  id INTEGER PRIMARY KEY,
  uuid TEXT NOT NULL UNIQUE DEFAULT (lower(hex(randomblob(16)))),
  title TEXT NOT NULL CHECK (length(trim(title)) > 0),
  title_key TEXT NOT NULL UNIQUE,
  is_head INTEGER NOT NULL DEFAULT 0 CHECK (is_head IN (0,1)),
  is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE employees (
  id INTEGER PRIMARY KEY,
  uuid TEXT NOT NULL UNIQUE DEFAULT (lower(hex(randomblob(16)))),
  last_name TEXT NOT NULL CHECK (length(trim(last_name)) > 0),
  first_name TEXT NOT NULL CHECK (length(trim(first_name)) > 0),
  middle_name TEXT CHECK (middle_name IS NULL OR length(trim(middle_name)) > 0),
  full_name TEXT GENERATED ALWAYS AS (
    last_name || ' ' || first_name || COALESCE(' ' || middle_name, '')
  ) VIRTUAL,
  birth_date TEXT CHECK (birth_date IS NULL OR date(birth_date) IS birth_date),
  email TEXT,
  phone TEXT,
  unit_id INTEGER NOT NULL REFERENCES units(id) ON DELETE RESTRICT,
  position_id INTEGER NOT NULL REFERENCES positions(id) ON DELETE RESTRICT,
  user_id INTEGER,
  status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','dismissed')),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE structure_log (
  id INTEGER PRIMARY KEY,
  ts TEXT NOT NULL DEFAULT (datetime('now')),
  actor TEXT NOT NULL,
  action TEXT NOT NULL,
  entity TEXT NOT NULL,
  entity_id INTEGER,
  before_json TEXT,
  after_json TEXT
);

CREATE INDEX idx_emp_unit ON employees(unit_id);
CREATE INDEX idx_emp_position ON employees(position_id);
CREATE INDEX idx_emp_name ON employees(last_name, first_name);
