<?php
class CustomObjectModel extends ObjectModel
{
    public function getDatabaseColumns()
    {
        $definition = ObjectModel::getDefinition($this);

        $sql = 'SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA="' . _DB_NAME_ . '" AND TABLE_NAME="' . _DB_PREFIX_ . $definition['table'] . '"';

        return Db::getInstance()->executeS($sql);
    }

    public function createColumn(
        $name,
        $column_definition
    )
    {
        $definition = ObjectModel::getDefinition($this);

        $sql = 'ALTER TABLE ' . _DB_PREFIX_ . $definition['table'];
        $sql .= ' ADD COLUMN ' . $name . ' ' . $column_definition['db_type'];

        if ($field_name === $definition['primary'])
        {
            $sql .= ' INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT';
        }
        else
        {
            if (isset($field['required']) && $field['required'])
            {
                $sql .= ' NOT NULL';
            }

            if (isset($field['default']))
            {
                $sql .= ' DEFAULT "' . $field['default'] . '"';
            }
        }

        Db::getInstance()->execute($sql);
    }

    public function createMissingColumns()
    {
        $columns    = $this->getDatabaseColumns();
        $definition = ObjectModel::getDefinition($this);

        foreach ($definition['fields'] as $column_name => $column_definition)
        {
            //column exists in database
            $exists = false;
            foreach ($columns as $column)
            {
                p($column);
                if ($column['COLUMN_NAME'] === $column_name)
                {
                    $exists = true;
                    break;
                }
            }

            if (!$exists)
            {
                $this->createColumn($column_name, $column_definition);
            }
        }
    }

    public function createDatabase()
    {
        $definition = ObjectModel::getDefinition($this);

        $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . $definition['table'] . ' (';
        $sql .= $definition['primary'] . ' INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,';

        foreach ($definition['fields'] as $field_name => $field)
        {
            if ($field_name === $definition['primary'])
            {
                continue;
            }

            $sql .= $field_name . ' ' . $field['db_type'];

            if (isset($field['required']) && $field['required'])
            {
                $sql .= ' NOT NULL';
            }

            if (isset($field['default']))
            {
                $sql .= ' DEFAULT "' . $field['default'] . '"';
            }

            $sql .= ',';
        }

        $sql = trim($sql, ',');
        $sql .= ')';

        Db::getInstance()->execute($sql);
    }
}
