<?php

class CrudCustomObjectModel extends ObjectModel
{
    /**
     * Return informations of the columns that exists in the
     * table relative to the ObjectModel. If the Model has multilang enabled,
     * this method also returns information about the multilang table.
     */
    public function getDatabaseColumns()
    {
        $definition = ObjectModel::getDefinition($this);

        $sql = 'SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA="' . _DB_NAME_ . '" AND TABLE_NAME="' . _DB_PREFIX_ . $definition['table'] . '"';

        $columns['self'] = Db::getInstance()->executeS($sql, true, false);

        $sql = 'SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA="' . _DB_NAME_ . '" AND TABLE_NAME="' . _DB_PREFIX_ . $definition['table'] . '_lang"';
        $columns['lang'] = Db::getInstance()->executeS($sql, true, false);


        return $columns;
    }

    /**
     * Add a column in the table relative to the ObjectModel.
     * This method uses the $definition property of the ObjectModel,
     * with some extra properties.
     *
     * Example:
     * 'table'        => 'tablename',
     * 'primary'      => 'id',
     * 'fields'       => [
     *     'id'     => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
     *     'number' => [
     *         'type'     => self::TYPE_STRING,
     *         'db_type'  => 'varchar(20)',
     *         'required' => true,
     *         'default'  => '25'
     *     ],
     * ],
     *
     * The primary column is created automatically as INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT. The other columns
     * require an extra parameter, with the type of the column in the database.
     *
     *
     */
    public function createColumn(
        $field_name,
        $column_definition
    )
    {
        $definition = ObjectModel::getDefinition($this);

        //object model has a multilang table
        $multilang = isset($definition['multilang']) && $definition['multilang'];

        if ($multilang && $column_definition['lang']) {
            $sql = 'ALTER TABLE ' . _DB_PREFIX_ . $definition['table'] . '_lang';
        } else {
            $sql = 'ALTER TABLE ' . _DB_PREFIX_ . $definition['table'];
        }

        $sql .= ' ADD COLUMN ' . $field_name . ' ' . $column_definition['db_type'];

        if ($field_name === $definition['primary'] && !$column_definition['lang']) {
            $sql .= ' INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT';
        } else {
            if (isset($field['required']) && $field['required']) {
                $sql .= ' NOT NULL';
            }

            if (isset($field['default'])) {
                $sql .= ' DEFAULT "' . $field['default'] . '"';
            }
        }

        Db::getInstance()->execute($sql);
    }

    /**
     *  Create in the database every column detailed in the $definition property that are
     *  missing in the database.
     */
    public function createMissingColumns()
    {
        $columns    = $this->getDatabaseColumns();
        $definition = ObjectModel::getDefinition($this);

        $multilang = isset($definition['multilang']) && $definition['multilang'];


        foreach ($definition['fields'] as $column_name => $column_definition) {
            //column exists in database
            $exists = false;


            if ($multilang && $column_definition['lang']) {
                //column exists in database
                foreach ($columns['lang'] as $column) {
                    if ($column['COLUMN_NAME'] === $column_name) {
                        $exists = true;
                        break;
                    }
                }
            } else {
                foreach ($columns['self'] as $column) {
                    if ($column['COLUMN_NAME'] === $column_name) {
                        $exists = true;
                        break;
                    }
                }
            }

            if (!$exists) {
                $this->createColumn($column_name, $column_definition);
            }
        }

        //verify the foreign keys in the multilang table
        if ($multilang) {
            //id_lang column
            $column_name = 'id_lang';
            $exists = false;
            foreach ($columns['lang'] as $column) {
                if ($column['COLUMN_NAME'] === $column_name) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $column_definition = ['lang' => true, 'db_type' => 'int unsigned'];
                $this->createColumn($column_name, $column_definition);
            }

            //foreign key column
            $column_name = $definition['primary'];
            $exists = false;
            foreach ($columns['lang'] as $column) {
                if ($column['COLUMN_NAME'] === $column_name) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $column_definition = ['lang' => true, 'db_type' => 'int unsigned'];
                $this->createColumn($column_name, $column_definition);
            }
        }
    }

    /**
     *  Create the database table with its columns. Similar to the createColumn() method.
     */
    public function createDatabase()
    {
        $definition = ObjectModel::getDefinition($this);

        $multilang = isset($definition['multilang']) && $definition['multilang'];


        $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . $definition['table'] . ' (';
        $sql .= $definition['primary'] . ' INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,';

        foreach ($definition['fields'] as $field_name => $field) {
            if ($field_name === $definition['primary']) {
                continue;
            }

            if ($multilang && $field['lang']) {
                continue;
            }

            $sql .= $field_name . ' ' . $field['db_type'];

            if (isset($field['required']) && $field['required']) {
                $sql .= ' NOT NULL';
            }

            if (isset($field['default'])) {
                $sql .= ' DEFAULT "' . $field['default'] . '"';
            }

            $sql .= ',';
        }

        $sql = trim($sql, ',');
        $sql .= ')';

        Db::getInstance()->execute($sql);

        //create multilang tables
        if ($multilang) {
            $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . $definition['table'] . '_lang (';
            $sql .= $definition['primary'] . ' INTEGER UNSIGNED NOT NULL,';
            $sql .= 'id_lang INTEGER UNSIGNED NOT NULL,';

            if ($definition['multilang_shop']) {
                $sql .= 'id_shop INTEGER UNSIGNED NOT NULL,';
            }

            foreach ($definition['fields'] as $field_name => $field) {
                if ($field_name === $definition['primary']) {
                    continue;
                }

                if (!$field['lang']) {
                    continue;
                }

                $sql .= $field_name . ' ' . $field['db_type'];

                if (isset($field['required']) && $field['required']) {
                    $sql .= ' NOT NULL';
                }

                if (isset($field['default'])) {
                    $sql .= ' DEFAULT "' . $field['default'] . '"';
                }

                $sql .= ',';
            }

            $sql = trim($sql, ',');
            $sql .= ')';

            Db::getInstance()->execute($sql);
        }

    }
    
    public function dropDatabase()
    {
        $definition = ObjectModel::getDefinition($this);
        $multilang = isset($definition['multilang']) && $definition['multilang'];

        $sql = 'DROP TABLE ' . _DB_PREFIX_ . $definition['table'];
        Db::getInstance()->execute($sql);

        if ($multilang) {
            $sql = 'DROP TABLE ' . _DB_PREFIX_ . $definition['table'] . '_lang';
            Db::getInstance()->execute($sql);
        }
    }
}
