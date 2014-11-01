<?php namespace Anomaly\Streams\Platform\Assignment;

use Anomaly\Streams\Platform\Addon\FieldType\FieldTypeAddon;
use Anomaly\Streams\Platform\Entry\EntryInterface;
use Anomaly\Streams\Platform\Model\EloquentModel;

class AssignmentModel extends EloquentModel
{

    /**
     * Do not use timestamps.
     *
     * @var bool
     */
    public $timestamps = false;


    /**
     * This model is translatable.
     *
     * @var bool
     */
    protected $translatable = true;

    /**
     * The foreign key for translations.
     *
     * @var string
     */
    protected $translationForeignKey = 'assignment_id';

    /**
     * The database table name.
     *
     * @var string
     */
    protected $table = 'streams_assignments';

    /**
     * Add an assignment.
     *
     * @param $sortOrder
     * @param $streamId
     * @param $fieldId
     * @param $label
     * @param $placeholder
     * @param $instructions
     * @param $isUnique
     * @param $isRequired
     * @param $isTranslatable
     * @param $isRevisionable
     * @return $this
     */
    public function add(
        $sortOrder,
        $streamId,
        $fieldId,
        $label,
        $placeholder,
        $instructions,
        $isUnique,
        $isRequired,
        $isTranslatable,
        $isRevisionable
    ) {
        $this->label           = $label;
        $this->field_id        = $fieldId;
        $this->stream_id       = $streamId;
        $this->is_unique       = $isUnique;
        $this->sort_order      = $sortOrder;
        $this->is_required     = $isRequired;
        $this->placeholder     = $placeholder;
        $this->instructions    = $instructions;
        $this->is_translatable = $isTranslatable;
        $this->is_revisionable = $isRevisionable;

        $this->save();

        return $this;
    }

    /**
     * Remove an assignment.
     *
     * @param $streamId
     * @param $fieldId
     * @return $this|bool
     */
    public function remove($streamId, $fieldId)
    {
        $assignment = $this->whereStreamId($streamId)->whereFieldId($fieldId)->first();

        if ($assignment) {
            $assignment->delete();

            return $this;
        }

        return false;
    }

    /**
     * Find orphaned assignments.
     *
     * @return mixed
     */
    public function findAllOrphaned()
    {
        return $this->select('streams_assignments.*')
            ->leftJoin('streams_streams', 'streams_assignments.stream_id', '=', 'streams_streams.id')
            ->leftJoin('streams_fields', 'streams_assignments.field_id', '=', 'streams_fields.id')
            ->whereNull('streams_streams.id')
            ->orWhereNull('streams_fields.id')
            ->get();
    }

    public function stream()
    {
        return $this->belongsTo('Anomaly\Streams\Platform\Stream\StreamModel', 'stream_id');
    }

    public function field()
    {
        return $this->belongsTo('Anomaly\Streams\Platform\Field\FieldModel');
    }

    public function type(EntryInterface $entry = null)
    {
        $type         = $this->field->type;
        $field        = $this->field->slug;
        $instructions = $this->instructions;

        $data = compact('type', 'field', 'instructions');

        $command = 'Anomaly\Streams\Platform\Addon\FieldType\Command\BuildFieldTypeCommand';

        $fieldType = $this->execute($command, $data);

        if ($entry and $fieldType instanceof FieldTypeAddon) {

            $fieldType->setValue($entry->getAttribute($fieldType->getColumnName(), false));
        }

        return $fieldType;
    }

    public function getSettingsAttribute($settings)
    {
        return json_decode($settings);
    }

    public function setSettingsAttribute($settings)
    {
        $this->attributes['settings'] = json_encode($settings);
    }

    public function getRulesAttribute($rules)
    {
        return json_decode($rules);
    }

    public function setRulesAttribute($rules)
    {
        $this->attributes['rules'] = json_encode($rules);
    }

    public function getFieldName()
    {
        return $this->field->name;
    }

    public function getFieldLabel()
    {
        return $this->label != trans($this->label) ? $this->label : $this->field->name;
    }

    public function getFieldPlaceholder()
    {
        return $this->placeholder != trans($this->placeholder) ? $this->placeholder : null;
    }

    public function newCollection(array $items = [])
    {
        return new AssignmentCollection($items);
    }

    public function decorate()
    {
        return new AssignmentPresenter($this);
    }
}
