<?php

namespace Uccello\ModuleDesigner\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Uccello\ModuleDesigner\Models\DesignedModule;
use Uccello\Core\Models\Uitype;
use Uccello\Core\Models\Module;
use Uccello\Core\Models\Tab;
use Uccello\Core\Models\Block;
use Uccello\Core\Models\Field;
use Uccello\Core\Models\Displaytype;
use Uccello\Core\Support\ModuleImport;

class MakeModuleCommand extends Command
{
    /**
     * The structure of the module.
     *
     * @var \StdClass
     */
    protected $module;

    /**
     * The default locale.
     *
     * @var string
     */
    protected $locale;

    /**
     * File system implementation
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or edit a module compatible with Uccello';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FileSystem $files)
    {
        parent::__construct();

        $this->files = $files;

        $this->locale = \Lang::getLocale();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->checkForDesignedModules();
    }

    /**
     * Check if modules are being designed and
     * ask user to choose a module to continue
     * or select another action to perform.
     *
     * @return void
     */
    protected function checkForDesignedModules()
    {
        // Get all designed modules
        $designedModules = DesignedModule::all();

        // If designed modules are found display several options
        if (count($designedModules) > 0) {
            $choices = [];
            $modules = [];

            // $createEditModuleChoice = 'Create or edit another module';
            $createEditModuleChoice = 'Create a new module';
            $removeDesignedModuleChoice = 'Remove a designed module from the list';

            foreach ($designedModules as $module) {
                // Get module name
                $name = $module->name;

                // Store module data
                $modules[$name] = $module->data;

                // Add module name to choices list
                $choices[] = $name;
            }

            // Add actions to choices list
            $availableChoices = array_merge($choices, [
                $createEditModuleChoice,
                $removeDesignedModuleChoice
            ]);

            // Ask the action to perform
            $choice = $this->choice('Some modules are being designed. Choose a module to continue or select an action to perform', $availableChoices);

            // Create or Edit another module
            if ($choice === $createEditModuleChoice) {
                $this->createModule();
            }
            // Remove designed module from the list
            elseif ($choice === $removeDesignedModuleChoice) {
                // Ask the user what designed module he wants to remove
                $designedModuleToDelete = $this->choice('What designed module do you want to remove from the list?', $choices);

                // Delete designed module
                DesignedModule::where('name', $designedModuleToDelete)->delete();
                $this->info('<comment>' . $designedModuleToDelete . '</comment> was deleted from the list');

                // Display the list again
                $this->checkForDesignedModules();
            }
            // Select module and continue
            else {
                $this->module = $modules[$choice];
                $this->line('<info>Selected module:</info> '.$choice);

                // Continue
                $this->chooseAction(1);
            }
        }
        // No designed module available, simply continue
        else {
            $this->chooseAction(0, true);
        }
    }

    /**
     * Ask the user what action he wants to perform
     *
     * @param int|null $defaultChoiceIndex
     * @return void
     */
    protected function chooseAction($defaultChoiceIndex = null, $canCreateModule = false)
    {
        // Default choices
        $choices = [
            'Create a new module',
            'Add a tab',
            'Add a block',
            'Add a field',
            'Add a related list',
            'Add a link',
            'Install module',
            'Exit'
        ];

        // Remove first choice if necessary
        $availableChoices = $choices;
        if (!$canCreateModule) {
            unset($availableChoices[0]);
        }

        if (empty($this->module)) {
            unset($availableChoices[1]);
            unset($availableChoices[2]);
            unset($availableChoices[3]);
            unset($availableChoices[4]);
            unset($availableChoices[5]);
            unset($availableChoices[6]);
        }

        $choice = $this->choice('What action do you want to perform?', $availableChoices, $defaultChoiceIndex);

        switch ($choice) {
            // Create a new module
            case $choices[0]:
                $this->createModule();
                break;

            // Add a tab
            case $choices[1]:
                $this->createTab();
                break;

            // Add a block
            case $choices[2]:
                $this->createBlock();
                break;

            // Add a field
            case $choices[3]:
                $this->createField();
                break;

            // Add a related list
            case $choices[4]:
                $this->createRelatedList();
                break;

            // Add a link
            case $choices[5]:
                $this->createLink();
                break;

            // Install module
            case $choices[6]:
                $this->installModule();
                break;

            // Exit
            case $choices[7]:
                // Do nothing
                break;
        }
    }

    /**
     * Check module existence or notice the user
     *
     * @return void
     */
    protected function checkModuleExistence()
    {
        if (empty($this->module)) {
            $this->error('You must create a module first');
            $this->chooseAction(0, true);
        }
    }

    /**
     * Ask the user information to make the skeleton of the module.
     *
     * @return void
     */
    protected function createModule()
    {
        $moduleName = $this->ask('What is the module name? (e.g. book-type)');

        // The snake_case function converts the given string to snake_case
        $moduleName = snake_case($moduleName);

        // If module name is not defined, ask again
        if (!$moduleName) {
            $this->error('You must specify a module name');
            return $this->createModule();
        }
        // Check if module name is only with alphanumeric characters
        elseif (!preg_match('`^[a-z0-9-]+$`', $moduleName)) {
            $this->error('You must use only alphanumeric characters in lowercase');
            return $this->createModule();
        }

        // Create an empty object
        $this->module = new \stdClass();
        $this->module->lang = new \StdClass();
        $this->module->lang->{$this->locale} = new \StdClass();

        // Name
        $this->module->name = kebab_case($moduleName);

        // Translation
        $this->module->lang->{$this->locale}->{$this->module->name} = $this->ask('Translation plural [' . $this->locale . ']');
        $this->module->lang->{$this->locale}->{'single.' . $this->module->name} = $this->ask('Translation single [' . $this->locale . ']');

        // Model class
        $defaultModelClass = 'App\\' . studly_case($moduleName); // The studly_case function converts the given string to StudlyCase
        $this->module->model = $this->ask('Model class', $defaultModelClass);

        // Package
        $this->module->package = null;

        // If the model class does not begin by App\, ask the user if he wants to create the module in an external package
        $modelClassParts = explode('\\', $this->module->model);
        if ($modelClassParts[0] !== 'App') {
            if ($this->confirm('Do you want to create this module in an external package?', true)) {
                // Select an external package
                $package = $this->selectPackage();
                if (!is_null($package)) {
                    $this->module->package = $package;
                }
            }
        }

        // Table name
        $this->module->tableName = $this->ask('Table name', str_plural($this->module->name));

        // Table prefix
        if (!empty($this->module->package)) {
            $packageParts = explode('/', $this->module->package);
            $packageName = array_pop($packageParts);
            $defaultPrefix = $packageName . '_';
        } else {
            $defaultPrefix = '';
        }
        $this->module->tablePrefix = $this->ask('Table prefix', $defaultPrefix);

        // Icon
        $this->module->icon = $this->ask('Material <comment>icon</comment> name (e.g. book)');

        // Is for administration
        $this->module->isForAdmin = $this->confirm('Is this module for <comment>administration</comment> panel?');

        // Link
        $this->module->route = $this->ask('Default route', 'uccello.list');

        // Display module data
        $this->table(
            [
                'Name',
                'Package',
                'Model',
                'Table',
                'Prefix',
                'Icon',
                'For admin',
                'Default route'
            ],
            [
                [
                    $this->module->name,
                    $this->module->package,
                    $this->module->model,
                    $this->module->tableName,
                    $this->module->tablePrefix,
                    $this->module->icon,
                    ($this->module->isForAdmin ? 'Yes' : 'No'),
                    $this->module->route
                ]
            ]
        );

        // If information is not correct, restart step
        $isCorrect = $this->confirm('Is this information correct?', true);
        if (!$isCorrect) {
            return $this->createModule();
        }

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a block)
        $this->chooseAction(1);
    }

    /**
     * Ask the user information to make the a new tab.
     *
     * @return void
     */
    protected function createTab()
    {
        // Check module existence
        $this->checkModuleExistence();

        // Initialize tabs list if necessary
        if (!isset($this->module->tabs)) {
            $this->module->tabs = [];
        }

        $tab = new \StdClass();
        $tab->blocks = [];

        // Label
        $defaultLabel = count($this->module->tabs) === 0 ? 'tab.main' : 'tab.tab' . count($this->module->tabs);
        $tab->label = $this->ask('Tab label (will be translated)', $defaultLabel);

        // Translation
        $this->module->lang->{$this->locale}->{$tab->label} = $this->ask('Translation [' . $this->locale . ']');

        // Icon
        $tab->icon = $this->ask('Icon CSS class name');

        // Sequence
        if (count($this->module->tabs) > 0) {

            $choices = [];
            foreach ($this->module->tabs as $moduleTab) {
                $choices[] = 'Before - ' . $moduleTab->label;
                $choices[] = 'After - ' . $moduleTab->label;
            }

            $position = $this->choice('Where do you want to add this tab?', $choices, $choices[count($choices)-1]);
            $tabIndex = floor(array_search($position, $choices) / 2);

            $tab->sequence = preg_match('`After`', $position) ? $tabIndex + 1 : $tabIndex;

        } else {
            $tab->sequence = 0;
        }

        // Update other blocks sequence
        foreach ($this->module->tabs as &$moduleTab) {
            if ($moduleTab->sequence >= $tab->sequence) {
                $moduleTab->sequence += 1;
            }
        }

        // Add tab
        $this->module->tabs[] = $tab;

        // Sort tabs by sequence
        usort($this->module->tabs, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a block)
        $this->chooseAction(2);
    }

    /**
     * Ask the user information to make the a new block.
     *
     * @return void
     */
    protected function createBlock()
    {
        // Check module existence
        $this->checkModuleExistence();

        // Select a tab
        $tab = $this->selectTab();

        // Initialize blocks list if necessary
        if (!isset($tab->blocks)) {
            $tab->blocks = [];
        }

        $block = new \StdClass();
        $block->data = new \StdClass();
        $block->fields = [];

        // Label
        $defaultLabel = count($tab->blocks) === 0 ? 'general' : 'block' . count($tab->blocks);
        $label = $this->ask('Block label (will be translated)', $defaultLabel);
        $block->label = 'block.' . $label;

        // Translation
        $this->module->lang->{$this->locale}->{$block->label} = $this->ask('Translation [' . $this->locale . ']');

        // Description
        if ($this->confirm('Do you want to add a <comment>description</comment>?')) {
            $block->data->description = $block->label . '.description';
            $this->module->lang->{$this->locale}->{$block->data->description} = $this->ask('Translation [' . $this->locale . ']');
        }

        // Icon
        $block->icon = $this->ask('Icon CSS class name');

        // Sequence
        if (count($tab->blocks) > 0) {

            $choices = [];
            foreach ($tab->blocks as $moduleBlock) {
                $choices[] = 'Before - ' . $moduleBlock->label;
                $choices[] = 'After - ' . $moduleBlock->label;
            }

            $position = $this->choice('Where do you want to add this block?', $choices, $choices[count($choices)-1]);
            $blockIndex = floor(array_search($position, $choices) / 2);

            $block->sequence = preg_match('`After`', $position) ? $blockIndex + 1 : $blockIndex;

        } else {
            $block->sequence = 0;
        }

        // Update other blocks sequence
        foreach ($tab->blocks as &$moduleBlock) {
            if ($moduleBlock->sequence >= $block->sequence) {
                $moduleBlock->sequence += 1;
            }
        }

        // Add block
        $tab->blocks[] = $block;

        // Sort blocks by sequence
        usort($tab->blocks, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a field)
        $this->chooseAction(3);
    }

    /**
     * Ask the user information to make a new field.
     *
     * @return void
     */
    protected function createField()
    {
        // Check module existence
        $this->checkModuleExistence();

        // Get all module fields
        $moduleFields = $this->getAllFields();

        // Select a block
        $block = $this->selectBlock();

        // Initialize fields list if necessary
        if (!isset($block->fields)) {
            $block->fields = [];
        }

        $field = new \StdClass();
        $field->data = new \StdClass();

        // Name
        $field->name = $this->ask('Field name');
        $field->name = snake_case($field->name);

        // Check if the name already exists
        foreach ($moduleFields as $moduleField) {
            if ($moduleField->name === $field->name) {
                $this->error("A field called $field->name already exists.");
                $this->chooseAction();
                return;
            }
        }

        // Translation
        $this->module->lang->{$this->locale}->{'field.' . $field->name} = $this->ask('Translation [' . $this->locale . ']');

        // Uitype
        $field->uitype = $this->choice('Choose an uitype', $this->getUitypes(), 'text');

        // Displaytype
        $field->displaytype = $this->choice('Choose a display type', $this->getDisplaytypes(), 'everywhere');

        // Ask the user if the field is required
        $required = $this->confirm('Is the field required?');
        if ($required) {
            $field->data->rules = "required";
        }

        $field->displayInFilter = $this->confirm('Display this field by default in the list view?', true);

        // Large
        $large = $this->confirm('Display the field in two columns?', false);
        if ($large) {
            $field->data->large = true;
        }

        // Default value
        $default = $this->ask('Default value');
        if (!is_null($default)) {
            $field->data->default = $default;
        }

        // Other rules
        $rules = $this->ask('Other rules (See https://laravel.com/docs/5.7/validation#available-validation-rules)');
        if (!is_null($rules)) {
            // Add to previous rules if defined
            if (!empty($field->data->rules)) {
                $rules = $field->data->rules . '|' . $rules;
            }

            $field->data->rules = $rules;
        }

        // Add specific options according to the selected uitype ($field is modified directly in the called function)
        uitype($field->uitype)->askFieldOptions($this->module, $field, $this->input, $this->output);

        // Sequence
        if (count($block->fields) > 0) {
            $choices = [];
            foreach ($block->fields as $blockField) {
                $choices[] = 'Before - ' . $blockField->name;
                $choices[] = 'After - ' . $blockField->name;
            }

            $position = $this->choice('Where do you want to add this field?', $choices, $choices[count($choices)-1]);
            $fieldIndex = floor(array_search($position, $choices) / 2);

            $field->sequence = preg_match('`After`', $position) ? $fieldIndex + 1 : $fieldIndex;

        } else {
            $field->sequence = 0;
        }

        // Update other fields sequence
        foreach ($block->fields as &$blockField) {
            if ($blockField->sequence >= $field->sequence) {
                $blockField->sequence += 1;
            }
        }

        // Add field
        $block->fields[] = $field;

        // Sort fields by sequence
        usort($block->fields, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a field)
        $this->chooseAction(3);
    }

    /**
     * Ask the user information to make a new related list.
     *
     * @return void
     */
    protected function createRelatedList()
    {
        // Check module existence
        $this->checkModuleExistence();

        if (!isset($this->module->relatedLists)) {
            $this->module->relatedLists = [];
        }

        $relatedList = new \StdClass();
        $relatedList->data = new \StdClass();

        // Label
        $relatedListIndex = count($this->module->relatedLists)+1;
        $defaultLabel = 'relatedlist'.$relatedListIndex;
        $label = $this->ask('Choose a label (will be translated)', $defaultLabel);
        $relatedList->label = 'relatedlist.' . $label;

        // Translation
        $this->module->lang->{$this->locale}->{$relatedList->label} = $this->ask('Translation [' . $this->locale . ']');

        // Type
        $relatedList->type = $this->choice('Choose a type', ['Relation n-1', 'Relation n-n']);
        $relatedList->type = str_replace('Relation ', '', $relatedList->type);

        // Related Module
        $relatedModule = $this->selectModule('Select the related module');
        $relatedList->related_module = $relatedModule->name;

        // Related field
        if ($relatedList->type === 'n-1') {
            $relatedField = $this->selectField($relatedModule);
            $relatedList->related_field = $relatedField->name;
        } else {
            $relatedList->related_field = null;
        }

        // Tab
        $displayInTab = $this->confirm('Do you want to display it in an existant tab? By default it will create a new tab.', false);
        if ($displayInTab) {
            $tab = $this->selectTab();
            $relatedList->tab = $tab->label;
        } else {
            $relatedList->tab = null;
        }

        // Method
        $defaultMethod = $relatedList->type === 'n-n' ? 'getRelatedList' : 'getDependentList';
        $relatedList->method = $this->ask('Choose a method', $defaultMethod);

        // Actions
        if ($relatedList->type === 'n-1') {
            $actionsChoices = [
                'add',
                'Nothing'
            ];
        } else {
            $actionsChoices = [
                'add',
                'select',
                'add,select',
                'Nothing'
            ];
        }
        $actionsAnswer = $this->choice('Choose available actions', $actionsChoices, 'Nothing');
        $relatedList->data->actions = $actionsAnswer === 'Nothing' ? [] : explode(",", $actionsAnswer);

        // Icon
        $relatedList->icon = $this->ask('Icon CSS class name');

        // Sequence
        if (count($this->module->relatedLists) > 0) {

            $choices = [];
            foreach ($this->module->relatedLists as $moduleRelatedList) {
                $choices[] = 'Before - ' . $moduleRelatedList->label;
                $choices[] = 'After - ' . $moduleRelatedList->label;
            }

            $position = $this->choice('Where do you want to add this related list?', $choices, $choices[count($choices)-1]);
            $relatedListIndex = floor(array_search($position, $choices) / 2);

            $relatedList->sequence = preg_match('`After`', $position) ? $relatedListIndex + 1 : $relatedListIndex;

        } else {
            $relatedList->sequence = 0;
        }

        // Update other related lists sequence
        foreach ($this->module->relatedLists as &$moduleRelatedList) {
            if ($moduleRelatedList->sequence >= $relatedList->sequence) {
                $moduleRelatedList->sequence += 1;
            }
        }

        // Add related list
        $this->module->relatedLists[] = $relatedList;

        // Sort fields by sequence
        usort($this->module->relatedLists, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a related list)
        $this->chooseAction(4);
    }

    /**
     * Ask the user information to make a new link.
     *
     * @return void
     */
    protected function createLink()
    {
        // Check module existence
        $this->checkModuleExistence();

        // Initialize links list if necessary
        if (!isset($this->module->links)) {
            $this->module->links = [];
        }

        $link = new \StdClass();
        $link->data = new \StdClass();

        // Label
        $defaultLabel = 'link' . count($this->module->links);
        $label = $this->ask('Link label (will be translated)', $defaultLabel);
        $link->label = 'link.' . $label;

        // Translation
        $this->module->lang->{$this->locale}->{$link->label} = $this->ask('Translation [' . $this->locale . ']');

        // Icon
        $link->icon = $this->ask('Icon CSS class name');

        // Type
        $link->type = $this->choice('Type of link', ['detail', 'detail.action'], 'detail');

        // URL
        $link->url = $this->ask('URL');

        // Action type
        $link->data->actionType = $this->choice('Action type', ['link', 'ajax', 'modal'], 'link');

        // Color
        $link->data->color = $this->choice('Button color', [
            'default',
            'primary',
            'success',
            'info',
            'warning',
            'danger',
            'red',
            'pink',
            'purple',
            'deep-purple',
            'indigo',
            'blue',
            'light-blue',
            'cyan',
            'teal',
            'green',
            'light-green',
            'lime',
            'yellow',
            'amber',
            'orange',
            'deep-orange',
            'brown',
            'grey',
            'blue-grey',
            'black'
        ], 'primary');

        // Confirm
        $confirm = $this->confirm('Do you want to show a confirm alert?', false);
        if ($confirm) {
            $link->data->confirm = true;

            $customize = $this->confirm('Do you want to customize the confirm dialog?', false);
            if ($customize) {
                $link->data->dialog = new \StdClass();

                $link->data->dialog->title = $this->ask('Title', 'Are you sure?');
                $link->data->dialog->confirmButtonText = $this->ask('Confirm button text', 'Yes');
                $link->data->dialog->confirmButtonColor = $this->ask('Confirm button color', '#DD6B55');
                $link->data->dialog->closeOnConfirm = $this->confirm('Close dialog on confirm?', true);
            }
        }

        // Add options according to action type
        switch ($link->data->actionType) {
            // Link
            case 'link':
                    // Target
                    $target = $this->ask('Link target (e.g. _blank)');
                    if (!is_null($target)) {
                        $link->data->target = $target;
                    }
                break;

            // Ajax
            case 'ajax':
                    $link->data->ajax = new \StdClass();

                    // HTTP method
                    $link->data->ajax->method = $this->choice('HTTP method', ['get', 'post', 'put', 'delete', 'head', 'patch', 'connect', 'options', 'trace'], 'get');

                    // Query params
                    $params = $this->ask('Query params');
                    if (!is_null($params)) {
                        $link->data->ajax->params = $params;
                    }

                    // Update DOM
                    $updateDom = $this->confirm('Do you want to update the DOM?', false);
                    if ($updateDom) {
                        // Element to update
                        $link->data->ajax->elementToUpdate = $this->ask('What is the DOM selector of the element to update? (e.g. .card:eq(1) .body)');
                    }
                break;

            // Modal
            case 'modal':
                    $link->data->modal = new \StdClass();
                    $link->data->modal->id = $this->ask('What is the id of the modal to show? (e.g. productModal)');
                break;
        }

        // Sequence
        if (count($this->module->links) > 0) {

            $choices = [];
            foreach ($this->module->links as $moduleLink) {
                $choices[] = 'Before - ' . $moduleLink->label;
                $choices[] = 'After - ' . $moduleLink->label;
            }

            $position = $this->choice('Where do you want to add this link?', $choices, $choices[count($choices)-1]);
            $linkIndex = floor(array_search($position, $choices) / 2);

            $link->sequence = preg_match('`After`', $position) ? $linkIndex + 1 : $linkIndex;

        } else {
            $link->sequence = 0;
        }

        // Update other links sequence
        foreach ($this->module->links as &$moduleLink) {
            if ($moduleLink->sequence >= $link->sequence) {
                $moduleLink->sequence += 1;
            }
        }

        // Add block
        $this->module->links[] = $link;

        // Sort blocks by sequence
        usort($this->module->links, [$this, 'sortBySequence']);

        // Save module structure
        $this->saveModuleStructure();

        // Ask user to choose another action (Default: Add a link)
        $this->chooseAction(5);
    }

    /**
     * Install module
     *
     * @return void
     */
    public function installModule()
    {
        // Check module existence
        $this->checkModuleExistence();

        $import = new ModuleImport($this->files);
        $import->install($this->module);
    }

    /**
     * Create or update a line into designed_modules table
     *
     * @return void
     */
    protected function saveModuleStructure()
    {
        $designedModule = DesignedModule::updateOrCreate(
            ['name' => $this->module->name],
            ['data' => $this->module]
        );
    }

    /**
     * Get all module fields
     *
     * @return array
     */
    protected function getAllFields()
    {
        $fields = [];

        foreach ($this->module->tabs as $tab) {
            foreach ($tab->blocks as $block) {
                if (isset($block->fields)) {
                    foreach ($block->fields as $field) {
                        $fields[] = $field;
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Get all uitypes
     *
     * @return array
     */
    protected function getUitypes()
    {
        $uitypes = [];

        foreach (Uitype::all() as $uitype) {
            $uitypes[] = $uitype->name;
        }

        // Sort by name
        sort($uitypes);

        return $uitypes;
    }

    /**
     * Get all displaytypes
     *
     * @return array
     */
    protected function getDisplaytypes()
    {
        $displaytypes = [];

        foreach (Displaytype::all() as $displaytype) {
            $displaytypes[] = $displaytype->name;
        }

        return $displaytypes;
    }

    /**
     * Ask user to select an existant tab
     *
     * @return \StdClass
     */
    protected function selectTab()
    {
        if (empty($this->module->tabs)) {
            $this->error('You must create a tab first');
            $this->chooseAction(1);
        }

        $tabs = $this->module->tabs;

        $choices = [];

        foreach($tabs as $tab) {
            $choices[] = $tab->label;
        }

        // We clone the array before to sort it to retrieve the good choice index
        $choices_orig = $choices;

        // Sort by label
        sort($choices);

        $choice = $this->choice('Choose the tab', $choices, count($choices) - 1);

        $index = array_search($choice, $choices_orig);

        return $tabs[$index];
    }

    /**
     * Ask the user the package in which he wants to create the new module
     *
     * @return string
     */
    protected function selectPackage()
    {
        $package = null;

        // Get all packages
        $choices = $this->getPackages();

        if (count($choices) > 0) {
            $choice = $this->choice('In which package do you want to create the module?', $choices);

            $index = array_search($choice, $choices);

            $package = $choices[$index];
        }

        return $package;
    }

    /**
     * Ask user to select an existant block
     *
     * @return \StdClass
     */
    protected function selectBlock()
    {
        $choices = [];

        $allBlocks = [];

        foreach ($this->module->tabs as $tab) {
            foreach($tab->blocks as $block) {
                $choices[] = $block->label;

                $allBlocks[] = $block;
            }
        }

        if (empty($allBlocks)) {
            $this->error('You must create a block first');
            $this->chooseAction(2);
        }

        $choice = $this->choice('Choose the block in which to add the field', $choices, count($choices) - 1);

        $index = array_search($choice, $choices);

        return $allBlocks[$index];
    }

    /**
     * Ask user to select an existant field
     *
     * @param Module $module
     * @return \StdClass
     */
    protected function selectField(Module $module)
    {
        $fields = $module->fields;

        $choices = [];

        foreach($fields as $field) {
            $choices[] = $field->name;
        }

        // We clone the array before to sort it to retrieve the good choice index
        $choices_orig = $choices;

        // Sort by name
        sort($choices);

        $choice = $this->choice('Choose the field', $choices, count($choices) - 1);

        $index = array_search($choice, $choices_orig);

        return $fields[$index];
    }

    /**
     * Ask the user to select a module
     *
     * @param string $message
     * @return string
     */
    protected function selectModule($message = null)
    {
        if (!$message) {
            $message = 'Choose the module in which to perform the action';
        }

        $modules = Module::whereNotNull('model_class')->orderBy('name')->get();

        $choices = [];
        foreach ($modules as $_module) {
            $choices[] = $_module->name;
        }

        // Add module itself if necessary
        if (!in_array($this->module->name, $choices)) {
            $choices[] = $this->module->name;
        }

        // We clone the array before to sort it to retrieve the good choice index
        $choices_orig = $choices;

        // Sort
        sort($choices);

        $choice = $this->choice($message, $choices);

        $index = array_search($choice, $choices_orig);

        return $modules[$index];
    }

    /**
     * Sort $a and $b by sequence
     *
     * @param \StdClass $a
     * @param \StdClass $b
     * @return int
     */
    protected function sortBySequence(\StdClass $a, \StdClass $b) {
        if ($a->sequence == $b->sequence) {
            return 0;
        }

        return ($a->sequence < $b->sequence) ? -1 : 1;
    }

    /**
     * Scans packages directory and returns the packages list with the following format: vendor/package
     *
     * @return array
     */
    protected function getPackages() {
        $packages = [];

        // Get packages list from
        $packagePath = base_path('packages');

        if (is_dir($packagePath)) {
            // First level directories are vendors
            $vendors = $this->files->directories($packagePath);

            foreach ($vendors as $vendor) {
                // Second level directories are packages
                $vendorPackages = $this->files->directories($vendor);

                foreach ($vendorPackages as $vendorPackage) {
                    $packages[] = $this->files->basename($vendor) . '/' . $this->files->basename($vendorPackage);
                }
            }
        }

        // Sort packages by name
        sort($packages);

        return $packages;
    }
}
