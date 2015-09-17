<?php

namespace Anomaly\Streams\Platform\Model;

use Anomaly\Streams\Platform\Addon\FieldType\FieldType;
use Anomaly\Streams\Platform\Ui\Form\Contract\FormRepositoryInterface;
use Anomaly\Streams\Platform\Ui\Form\FormBuilder;

/**
 * Class EloquentFormRepositoryInterface.
 *
 * @link          http://anomaly.is/streams-platform
 * @author        AnomalyLabs, Inc. <hello@anomaly.is>
 * @author        Ryan Thompson <ryan@anomaly.is>
 * @package       Anomaly\Streams\Platform\Model
 */
class EloquentFormRepository implements FormRepositoryInterface
{
    /**
     * The form model.
     *
     * @var EloquentModel
     */
    protected $model;

    /**
     * Create a new EloquentFormRepositoryInterface instance.
     *
     * @param EloquentModel $model
     */
    public function __construct(EloquentModel $model)
    {
        $this->model = $model;
    }

    /**
     * Find an entry.
     *
     * @param $id
     * @return EloquentModel
     */
    public function findOrNew($id)
    {
        return $this->model->findOrNew($id);
    }

    /**
     * Save the form.
     *
     * @param FormBuilder $builder
     */
    public function save(FormBuilder $builder)
    {
        $form = $builder->getForm();

        $entry = $form->getEntry();

        $data = $this->prepareValueData($builder);

        if ($entry->getId()) {
            $entry->update($data);
        } else {
            $entry = $entry->create($data);
        }

        $form->setEntry($entry);

        $this->processSelfHandlingFields($builder);
    }

    /**
     * Prepare the value data for update / create.
     *
     * @param FormBuilder $builder
     * @return array
     */
    protected function prepareValueData(FormBuilder $builder)
    {
        $form = $builder->getForm();

        $entry  = $form->getEntry();
        $fields = $form->getFields();

        $allowed  = $fields->allowed();
        $disabled = $fields->disabled();

        /*
         * Set initial data from the
         * entry, minus undesired data.
         */
        $data = array_diff_key(
            $entry->getUnguardedAttributes(),
            array_merge(
                ['id', 'created_at', 'created_by', 'updated_at', 'updated_by'],
                array_flip($disabled->fieldSlugs())
            )
        );

        /**
         * Save default translation input.
         *
         * @var FieldType
         */
        foreach ($allowed->notTranslatable() as $field) {
            if (! $field->getLocale()) {
                array_set($data, $field->getField(), $form->getValue($field->getInputName()));
            }
        }

        /*
         * Loop through available translations
         * and save translated input.
         *
         * @var FieldType $field
         */
        if ($entry->getTranslationModel()) {
            foreach (config('streams::locales.enabled') as $locale) {
                foreach ($allowed->translatable() as $field) {
                    if ($field->getLocale() == $locale) {
                        array_set(
                            $data,
                            $locale.'.'.$field->getField(),
                            $form->getValue($field->getInputName())
                        );
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Process fields that handle themselves.
     *
     * @param FormBuilder $builder
     */
    protected function processSelfHandlingFields(FormBuilder $builder)
    {
        $form = $builder->getForm();

        $entry  = $form->getEntry();
        $fields = $form->getFields();

        $fields = $fields->selfHandling();

        /* @var FieldType $field */
        foreach ($fields as $field) {
            app()->call([$field->setEntry($entry), 'handle'], compact('builder'));
        }
    }
}
