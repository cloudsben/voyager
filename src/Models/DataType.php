<?php

namespace TCG\Voyager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Facades\DBSchema;

class DataType extends Model
{
    protected $table = 'data_types';

    protected $guarded = [];

    public function rows()
    {
        return $this->hasMany(DataRow::class);
    }

    public function browseRows()
    {
        return $this->rows()->where('browse', '=', 1);
    }

    public function readRows()
    {
        return $this->rows()->where('read', '=', 1);
    }

    public function editRows()
    {
        return $this->rows()->where('edit', '=', 1);
    }

    public function addRows()
    {
        return $this->rows()->where('add', '=', 1);
    }

    public function deleteRows()
    {
        return $this->rows()->where('delete', '=', 1);
    }

    public function setGeneratePermissionsAttribute($value)
    {
        $this->attributes['generate_permissions'] = $value ? 1 : 0;
    }

    public function setServerSideAttribute($value)
    {
        $this->attributes['server_side'] = $value ? 1 : 0;
    }

    public function updateDataType($requestData)
    {
        $success = true;
        $fields = $this->fields(array_get($requestData, 'name'));

        $dataTypeData = array_filter(
            $requestData,
            function ($value, $key) {
                return strpos($key, 'field_') !== 0;
            },
            ARRAY_FILTER_USE_BOTH
        );
        $success = $success && $this->fill($dataTypeData)->save();

        foreach ($fields as $field) {
            $dataRow = DataRow::where('data_type_id', '=', $this->id)
                              ->where('field', '=', $field)
                              ->first();

            if (!isset($dataRow->id)) {
                $dataRow = new DataRow();
            }

            $dataRow->data_type_id = $this->id;
            $dataRow->required = $requestData['field_required_'.$field];

            foreach (['browse', 'read', 'edit', 'add', 'delete'] as $check) {
                if (isset($requestData["field_{$check}_{$field}"])) {
                    $dataRow->{$check} = 1;
                } else {
                    $dataRow->{$check} = 0;
                }
            }

            $dataRow->field = $requestData['field_'.$field];
            $dataRow->type = $requestData['field_input_type_'.$field];
            $dataRow->details = $requestData['field_details_'.$field];
            $dataRow->display_name = $requestData['field_display_name_'.$field];
            $dataRowSuccess = $dataRow->save();

            // If success has never failed yet, let's add DataRowSuccess to success
            if ($success !== false) {
                $success = $dataRowSuccess;
            }
        }

        if ($this->generate_permissions) {
            Permission::generateFor($this->name);
        }

        return $success !== false;
    }

    public function fields($name = null)
    {
        if (is_null($name)) {
            $name = $this->name;
        }

        $fields = Schema::getColumnListing($name);

        if ($extraFields = $this->extraFields()) {
            foreach ($extraFields as $field) {
                $fields[] = $field['Field'];
            }
        }

        return $fields;
    }

    public function fieldOptions()
    {
        $table = $this->name;

        $fieldOptions = DBSchema::describeTable($table);

        if ($extraFields = $this->extraFields()) {
            foreach ($extraFields as $field) {
                $fieldOptions[] = (object) $field;
            }
        }

        return $fieldOptions;
    }

    public function extraFields()
    {
        $model = app($this->model_name);

        if (method_exists($model, 'adminFields')) {
            return $model->adminFields();
        }
    }
}
