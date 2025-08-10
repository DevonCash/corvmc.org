<?php

use Filament\Actions\View\ActionsIconAlias;
use Filament\Forms\View\FormsIconAlias;
use Filament\Infolists\View\InfolistsIconAlias;
use Filament\Notifications\View\NotificationsIconAlias;
use Filament\View\PanelsIconAlias;
use Filament\Schemas\View\SchemaIconAlias;
use Filament\Tables\View\TablesIconAlias;
use Filament\Support\View\SupportIconAlias;
use Filament\Widgets\View\WidgetsIconAlias;

return [
    /*
    |--------------------------------------------------------------------------
    | Filament Tabler Icon Mappings
    |--------------------------------------------------------------------------
    |
    | This configuration maps ALL Filament v4 icon aliases to Tabler icons using
    | enum constants as keys for type safety and maintainability.
    |
    */

    // ActionsIconAlias - All 25 constants
    ActionsIconAlias::ACTION_GROUP => 'tabler-dots',
    ActionsIconAlias::CREATE_ACTION_GROUPED => 'tabler-plus',
    ActionsIconAlias::DELETE_ACTION => 'tabler-trash',
    ActionsIconAlias::DELETE_ACTION_GROUPED => 'tabler-trash',
    ActionsIconAlias::DELETE_ACTION_MODAL => 'tabler-trash',
    ActionsIconAlias::DETACH_ACTION => 'tabler-unlink',
    ActionsIconAlias::DETACH_ACTION_MODAL => 'tabler-unlink',
    ActionsIconAlias::DISSOCIATE_ACTION => 'tabler-x',
    ActionsIconAlias::DISSOCIATE_ACTION_MODAL => 'tabler-x',
    ActionsIconAlias::EDIT_ACTION => 'tabler-pencil',
    ActionsIconAlias::EDIT_ACTION_GROUPED => 'tabler-pencil',
    ActionsIconAlias::EXPORT_ACTION_GROUPED => 'tabler-download',
    ActionsIconAlias::FORCE_DELETE_ACTION => 'tabler-trash-x',
    ActionsIconAlias::FORCE_DELETE_ACTION_GROUPED => 'tabler-trash-x',
    ActionsIconAlias::FORCE_DELETE_ACTION_MODAL => 'tabler-trash-x',
    ActionsIconAlias::IMPORT_ACTION_GROUPED => 'tabler-upload',
    ActionsIconAlias::MODAL_CONFIRMATION => 'tabler-help-circle',
    ActionsIconAlias::REPLICATE_ACTION => 'tabler-copy',
    ActionsIconAlias::REPLICATE_ACTION_GROUPED => 'tabler-copy',
    ActionsIconAlias::RESTORE_ACTION => 'tabler-refresh',
    ActionsIconAlias::RESTORE_ACTION_GROUPED => 'tabler-refresh',
    ActionsIconAlias::RESTORE_ACTION_MODAL => 'tabler-refresh',
    ActionsIconAlias::VIEW_ACTION => 'tabler-eye',
    ActionsIconAlias::VIEW_ACTION_GROUPED => 'tabler-eye',

    // FormsIconAlias - All 39 constants
    FormsIconAlias::COMPONENTS_BUILDER_ACTIONS_CLONE => 'tabler-copy',
    FormsIconAlias::COMPONENTS_BUILDER_ACTIONS_COLLAPSE => 'tabler-chevron-up',
    FormsIconAlias::COMPONENTS_BUILDER_ACTIONS_DELETE => 'tabler-trash',
    FormsIconAlias::COMPONENTS_BUILDER_ACTIONS_EXPAND => 'tabler-chevron-down',
    FormsIconAlias::COMPONENTS_BUILDER_ACTIONS_MOVE_DOWN => 'tabler-chevron-down',
    FormsIconAlias::COMPONENTS_BUILDER_ACTIONS_MOVE_UP => 'tabler-chevron-up',
    FormsIconAlias::COMPONENTS_BUILDER_ACTIONS_REORDER => 'tabler-grip-vertical',
    FormsIconAlias::COMPONENTS_CHECKBOX_LIST_SEARCH_FIELD => 'tabler-search',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_DRAG_CROP => 'tabler-crop',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_DRAG_MOVE => 'tabler-arrows-move',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_FLIP_HORIZONTAL => 'tabler-flip-horizontal',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_FLIP_VERTICAL => 'tabler-flip-vertical',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_MOVE_DOWN => 'tabler-arrow-down',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_MOVE_LEFT => 'tabler-arrow-left',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_MOVE_RIGHT => 'tabler-arrow-right',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_MOVE_UP => 'tabler-arrow-up',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_ROTATE_LEFT => 'tabler-rotate-2',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_ROTATE_RIGHT => 'tabler-rotate-clockwise-2',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_ZOOM_100 => 'tabler-zoom-reset',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_ZOOM_IN => 'tabler-zoom-in',
    FormsIconAlias::COMPONENTS_FILE_UPLOAD_EDITOR_ACTIONS_ZOOM_OUT => 'tabler-zoom-out',
    FormsIconAlias::COMPONENTS_KEY_VALUE_ACTIONS_DELETE => 'tabler-trash',
    FormsIconAlias::COMPONENTS_KEY_VALUE_ACTIONS_REORDER => 'tabler-grip-vertical',
    FormsIconAlias::COMPONENTS_REPEATER_ACTIONS_CLONE => 'tabler-copy',
    FormsIconAlias::COMPONENTS_REPEATER_ACTIONS_COLLAPSE => 'tabler-chevron-up',
    FormsIconAlias::COMPONENTS_REPEATER_ACTIONS_DELETE => 'tabler-trash',
    FormsIconAlias::COMPONENTS_REPEATER_ACTIONS_EXPAND => 'tabler-chevron-down',
    FormsIconAlias::COMPONENTS_REPEATER_ACTIONS_MOVE_DOWN => 'tabler-chevron-down',
    FormsIconAlias::COMPONENTS_REPEATER_ACTIONS_MOVE_UP => 'tabler-chevron-up',
    FormsIconAlias::COMPONENTS_REPEATER_ACTIONS_REORDER => 'tabler-grip-vertical',
    FormsIconAlias::COMPONENTS_RICH_EDITOR_PANELS_CUSTOM_BLOCKS_CLOSE_BUTTON => 'tabler-x',
    FormsIconAlias::COMPONENTS_RICH_EDITOR_PANELS_CUSTOM_BLOCK_DELETE_BUTTON => 'tabler-trash',
    FormsIconAlias::COMPONENTS_RICH_EDITOR_PANELS_CUSTOM_BLOCK_EDIT_BUTTON => 'tabler-pencil',
    FormsIconAlias::COMPONENTS_RICH_EDITOR_PANELS_MERGE_TAGS_CLOSE_BUTTON => 'tabler-x',
    FormsIconAlias::COMPONENTS_SELECT_ACTIONS_CREATE_OPTION => 'tabler-plus',
    FormsIconAlias::COMPONENTS_SELECT_ACTIONS_EDIT_OPTION => 'tabler-pencil',
    FormsIconAlias::COMPONENTS_TEXT_INPUT_ACTIONS_HIDE_PASSWORD => 'tabler-eye-off',
    FormsIconAlias::COMPONENTS_TEXT_INPUT_ACTIONS_SHOW_PASSWORD => 'tabler-eye',
    FormsIconAlias::COMPONENTS_TOGGLE_BUTTONS_BOOLEAN_FALSE => 'tabler-x',
    FormsIconAlias::COMPONENTS_TOGGLE_BUTTONS_BOOLEAN_TRUE => 'tabler-check',

    // InfolistsIconAlias - All 2 constants
    InfolistsIconAlias::COMPONENTS_ICON_ENTRY_FALSE => 'tabler-x',
    InfolistsIconAlias::COMPONENTS_ICON_ENTRY_TRUE => 'tabler-check',

    // NotificationsIconAlias - All 6 constants
    NotificationsIconAlias::DATABASE_MODAL_EMPTY_STATE => 'tabler-bell-off',
    NotificationsIconAlias::NOTIFICATION_CLOSE_BUTTON => 'tabler-x',
    NotificationsIconAlias::NOTIFICATION_DANGER => 'tabler-exclamation-circle',
    NotificationsIconAlias::NOTIFICATION_INFO => 'tabler-info-circle',
    NotificationsIconAlias::NOTIFICATION_SUCCESS => 'tabler-circle-check',
    NotificationsIconAlias::NOTIFICATION_WARNING => 'tabler-alert-triangle',

    // PanelsIconAlias - All 27 constants
    PanelsIconAlias::GLOBAL_SEARCH_FIELD => 'tabler-search',
    PanelsIconAlias::PAGES_DASHBOARD_ACTIONS_FILTER => 'tabler-filter',
    PanelsIconAlias::PAGES_DASHBOARD_NAVIGATION_ITEM => 'tabler-layout-dashboard',
    PanelsIconAlias::PAGES_PASSWORD_RESET_REQUEST_PASSWORD_RESET_ACTIONS_LOGIN => 'tabler-login',
    PanelsIconAlias::PAGES_PASSWORD_RESET_REQUEST_PASSWORD_RESET_ACTIONS_LOGIN_RTL => 'tabler-login',
    PanelsIconAlias::RESOURCES_PAGES_EDIT_RECORD_NAVIGATION_ITEM => 'tabler-pencil',
    PanelsIconAlias::RESOURCES_PAGES_MANAGE_RELATED_RECORDS_NAVIGATION_ITEM => 'tabler-link',
    PanelsIconAlias::RESOURCES_PAGES_VIEW_RECORD_NAVIGATION_ITEM => 'tabler-eye',
    PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => 'tabler-panel-left-close',
    PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL => 'tabler-panel-right-close',
    PanelsIconAlias::SIDEBAR_EXPAND_BUTTON => 'tabler-panel-left-open',
    PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL => 'tabler-panel-right-open',
    PanelsIconAlias::SIDEBAR_GROUP_COLLAPSE_BUTTON => 'tabler-chevron-up',
    PanelsIconAlias::TENANT_MENU_BILLING_BUTTON => 'tabler-credit-card',
    PanelsIconAlias::TENANT_MENU_PROFILE_BUTTON => 'tabler-user',
    PanelsIconAlias::TENANT_MENU_REGISTRATION_BUTTON => 'tabler-user-plus',
    PanelsIconAlias::TENANT_MENU_TOGGLE_BUTTON => 'tabler-chevron-down',
    PanelsIconAlias::THEME_SWITCHER_LIGHT_BUTTON => 'tabler-brightness-2',
    PanelsIconAlias::THEME_SWITCHER_DARK_BUTTON => 'tabler-brightness-half',
    PanelsIconAlias::THEME_SWITCHER_SYSTEM_BUTTON => 'tabler-brightness-auto',
    PanelsIconAlias::TOPBAR_CLOSE_SIDEBAR_BUTTON => 'tabler-x',
    PanelsIconAlias::TOPBAR_OPEN_SIDEBAR_BUTTON => 'tabler-menu-2',
    PanelsIconAlias::TOPBAR_GROUP_TOGGLE_BUTTON => 'tabler-chevron-down',
    PanelsIconAlias::TOPBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON => 'tabler-bell',
    PanelsIconAlias::USER_MENU_PROFILE_ITEM => 'tabler-user',
    PanelsIconAlias::USER_MENU_LOGOUT_BUTTON => 'tabler-logout',
    PanelsIconAlias::WIDGETS_ACCOUNT_LOGOUT_BUTTON => 'tabler-logout',
    PanelsIconAlias::WIDGETS_FILAMENT_INFO_OPEN_DOCUMENTATION_BUTTON => 'tabler-book',
    PanelsIconAlias::WIDGETS_FILAMENT_INFO_OPEN_GITHUB_BUTTON => 'tabler-brand-github',

    // SchemaIconAlias - All 1 constant
    SchemaIconAlias::COMPONENTS_WIZARD_COMPLETED_STEP => 'tabler-check',

    // SupportIconAlias - All 13 constants
    SupportIconAlias::BADGE_DELETE_BUTTON => 'tabler-x',
    SupportIconAlias::BREADCRUMBS_SEPARATOR => 'tabler-chevron-right',
    SupportIconAlias::BREADCRUMBS_SEPARATOR_RTL => 'tabler-chevron-left',
    SupportIconAlias::MODAL_CLOSE_BUTTON => 'tabler-x',
    SupportIconAlias::PAGINATION_FIRST_BUTTON => 'tabler-chevrons-left',
    SupportIconAlias::PAGINATION_FIRST_BUTTON_RTL => 'tabler-chevrons-right',
    SupportIconAlias::PAGINATION_LAST_BUTTON => 'tabler-chevrons-right',
    SupportIconAlias::PAGINATION_LAST_BUTTON_RTL => 'tabler-chevrons-left',
    SupportIconAlias::PAGINATION_NEXT_BUTTON => 'tabler-chevron-right',
    SupportIconAlias::PAGINATION_NEXT_BUTTON_RTL => 'tabler-chevron-left',
    SupportIconAlias::PAGINATION_PREVIOUS_BUTTON => 'tabler-chevron-left',
    SupportIconAlias::PAGINATION_PREVIOUS_BUTTON_RTL => 'tabler-chevron-right',
    SupportIconAlias::SECTION_COLLAPSE_BUTTON => 'tabler-chevron-up',

    // TablesIconAlias - All 24 constants
    TablesIconAlias::ACTIONS_DISABLE_REORDERING => 'tabler-arrows-sort',
    TablesIconAlias::ACTIONS_ENABLE_REORDERING => 'tabler-arrows-sort',
    TablesIconAlias::ACTIONS_FILTER => 'tabler-filter',
    TablesIconAlias::ACTIONS_GROUP => 'tabler-layout-grid',
    TablesIconAlias::ACTIONS_OPEN_BULK_ACTIONS => 'tabler-chevron-down',
    TablesIconAlias::ACTIONS_COLUMN_MANAGER => 'tabler-columns',
    TablesIconAlias::COLUMNS_COLLAPSE_BUTTON => 'tabler-chevron-up',
    TablesIconAlias::COLUMNS_ICON_COLUMN_FALSE => 'tabler-x',
    TablesIconAlias::COLUMNS_ICON_COLUMN_TRUE => 'tabler-check',
    TablesIconAlias::EMPTY_STATE => 'tabler-database-off',
    TablesIconAlias::FILTERS_QUERY_BUILDER_CONSTRAINTS_BOOLEAN => 'tabler-toggle-left',
    TablesIconAlias::FILTERS_QUERY_BUILDER_CONSTRAINTS_DATE => 'tabler-calendar',
    TablesIconAlias::FILTERS_QUERY_BUILDER_CONSTRAINTS_NUMBER => 'tabler-hash',
    TablesIconAlias::FILTERS_QUERY_BUILDER_CONSTRAINTS_RELATIONSHIP => 'tabler-link',
    TablesIconAlias::FILTERS_QUERY_BUILDER_CONSTRAINTS_SELECT => 'tabler-chevron-down',
    TablesIconAlias::FILTERS_QUERY_BUILDER_CONSTRAINTS_TEXT => 'tabler-abc',
    TablesIconAlias::FILTERS_REMOVE_ALL_BUTTON => 'tabler-x',
    TablesIconAlias::GROUPING_COLLAPSE_BUTTON => 'tabler-chevron-up',
    TablesIconAlias::HEADER_CELL_SORT_ASC_BUTTON => 'tabler-chevron-up',
    TablesIconAlias::HEADER_CELL_SORT_BUTTON => 'tabler-arrows-sort',
    TablesIconAlias::HEADER_CELL_SORT_DESC_BUTTON => 'tabler-chevron-down',
    TablesIconAlias::REORDER_HANDLE => 'tabler-grip-vertical',
    TablesIconAlias::SEARCH_FIELD => 'tabler-search',

    // WidgetsIconAlias - All 1 constant
    WidgetsIconAlias::CHART_WIDGET_FILTER => 'tabler-filter',
];
