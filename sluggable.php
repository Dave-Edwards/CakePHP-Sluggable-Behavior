<?php

/**
 * CakePHP Sluggable Behavior
 *
 * Makes generation of unique URL slugs for permalinks very simple in CakePHP
 *
 * @version 2.0
 * @package CakePHP_Sluggable
 * @author Aaron Pollock <aaron.pollock@gmail.com>
 * @copyright Copyright (c) 2011-12, Aaron Pollock
 * @license http://creativecommons.org/licenses/by/3.0/ Creative Commons Attribution 3.0 Unported
 */

/**
 * The main Behavior class
 *
 * @package CakePHP_Sluggable
 */
class SluggableBehavior extends ModelBehavior {

	/**
	 * Settings as specified in the Cake model where the Behavior is applied
	 *
	 * @var array
	 * @access public
	 */
	public $settings = array();

	/**
	 * Set up the Behavior settings
	 *
	 * @access public
	 * @param mixed $model Instance of the model which has this Behavior
	 * @return void
	 */
	public function setup(Model $model, $settings = array())
	{
		if (!isset($this -> settings[$model -> alias])) {
			$this -> settings[$model -> alias] = array(
				'slug_field'		=> 'slug',
				'separator'		=> '-',
				'title_field'		=> $model -> displayField,
                                'update_existing'       => 'false'
			);
		}

		$this -> settings[$model -> alias] = array_merge(
			$this -> settings[$model -> alias],
			(array)$settings
		);
	}

	/**
	 * Callback for before validation of associated model
	 *
	 * Checks if a slug should be generated and, if so, puts it into the model's data before validation
	 *
	 * @param mixed $model Instance of the model which has this Behavior
	 * @return bool Always returns true, allowing CakePHP validation to proceed
	 */
	public function beforeValidate(Model $model, $options = array())
	{
		if (!$this -> _slug_override_in_place($model) && $this -> _record_needs_slug($model)){
			$this -> _generate_slug($model);
		}

		return true;
	}

	/**
	 * Check if the *loaded* model data already contains a slug
	 *
	 * This will apply if the app has specified a slug manually, rather than having this behavior generate it
	 *
	 * @param mixed $model Instance of the model which has this Behavior
	 * @return bool True if data already contains slug
	 */
	private function _slug_override_in_place(Model $model)
	{
		if (isset($model -> data[$model -> alias][$this -> settings[$model -> alias]['slug_field']])){
			$slug_in_data = $model -> data[$model -> alias][$this -> settings[$model -> alias]['slug_field']];
		}

		return (isset($slug_in_data) && !empty($slug_in_data));
	}

	/**
	 * Check if the record in the database needs a slug generated
	 *
	 * @param mixed $model Instance of the model which has this Behavior
	 * @return bool True if a slug is required (false if one exists)
	 */
	private function _record_needs_slug(Model $model)
	{
		if ($model -> id == FALSE) {

			// new record, needs a slug in all cases
			return true;

		} else {

			// existing record; check to see if slug already present before generating one, or whether update_existing option has been set to overwrite an existing one on title change
			$existing_data = $model -> findById($model -> id);
			$existing_slug = $existing_data[$model -> alias][$this -> settings[$model -> alias]['slug_field']];
			$existing_title = $existing_data[$model -> alias][$this -> settings[$model -> alias]['title_field']];
                        $new_title = $model->data[$model -> alias][$this -> settings[$model -> alias]['title_field']];

			return ( null === $existing_slug || '' === $existing_slug || ($this->settings[$model -> alias]['update_existing'] === 'true' && $existing_title != $new_title));

		}
	}

	/**
	 * Generate the slug based on the specified source field, and assign it in the model data to the specified slug field
	 *
	 * @param mixed $model Instance of the model which has this Behavior
	 * @return mixed Nothing is returned
	 */
	private function _generate_slug(Model $model)
	{
		$title_field = $this -> settings[$model -> alias]['title_field'];

		// use the record title as passed in the data being validated
		if (isset($model -> data[$model -> alias][$title_field])) {
			$slug_source = $model -> data[$model -> alias][$title_field];

		// use the record title in the database
		} elseif (isset($model -> id) && $model -> field($title_field)) {
			$slug_source = $model -> field($title_field);

		}

		if (isset($slug_source)) {

			$slug = strtolower(Inflector::slug($slug_source, $this -> settings[$model -> alias]['separator']));
			$max_length = $model -> _schema[$this -> settings[$model -> alias]['slug_field']]['length'];
			if (strlen($slug) > $max_length) {
				$slug = substr($slug, 0, $max_length);
			}

			$this -> duplicate_suffix = 0;
			$slug = $this -> _deduplicate_slug($model, $slug);
			$model->data[$model -> alias][$this -> settings[$model -> alias]['slug_field']] = $slug;

		}
	}

	/**
	 * Recursive function which keeps incrementing a slug suffix until it is unique, before returning the result
	 *
	 * @param mixed $model Instance of the model which has this Behavior
	 * @param string $slug The generated slug which needs checked for uniqueness and amended if required
	 * @return string A final slug which has been found to be unique
	 */
	private function _deduplicate_slug(Model $model, &$slug)
	{
		$dupes = $model -> find(
			'count',
			array(
				'conditions' => array(
					$model -> alias . '.' . $this -> settings[$model -> alias]['slug_field'] => $slug,
					$model -> alias . '.id !=' => $model -> id
				)
			)
		);

		if (0 === $dupes) {

			return $slug;

		} else {

			$previous_suffix = $this -> duplicate_suffix;
			$this -> duplicate_suffix++;

			$new_slug_suffix = $this -> settings[$model -> alias]['separator'].(string)$this -> duplicate_suffix;
			$new_suffix_length = strlen($new_slug_suffix);
			$slug_length = strlen($slug);
			$max_length = $model -> _schema[$this -> settings[$model -> alias]['slug_field']]['length'];

			if ($previous_suffix > 0 || $new_suffix_length + $slug_length > $max_length) {
				$replace_at = -1 * strlen($previous_suffix) -1;
			} else {
				$replace_at = $slug_length;
			}

			$slug = substr_replace($slug, $new_slug_suffix, $replace_at);
			return $this -> _deduplicate_slug($model, $slug);

		}
	}

}
