<?php

/**
 * The school's own identity, as it appears on printed documents.
 *
 * Separate from config('app.name'), which is the SYSTEM name ("Rahai School
 * Management System") and belongs on a browser tab, not on a report card.
 *
 * Every document header reads these values, so the strings live here once
 * rather than being retyped into each template. A published academic record
 * SNAPSHOTS them at the moment of issue: renaming the foundation, or appointing
 * a new principal, must never rewrite a document a family already holds.
 *
 * Deliberately a config file rather than a school_settings table. Nothing here
 * changes often, and a table would need a screen, a policy and a migration to
 * serve a handful of strings. When the school wants to edit these itself
 * without a deploy, that is a settings feature and can be built then.
 */
return [
    'name' => env('SCHOOL_NAME', 'Rahai School'),

    // The foundation line printed beneath the school name.
    'line2' => env('SCHOOL_LINE2', 'Yayasan Pendidikan Halmahera Membangun Bangsa'),

    'address' => env('SCHOOL_ADDRESS', ''),

    'contact' => env('SCHOOL_CONTACT', ''),

    /*
     * The signatory. Deliberately NOT derived from the `principal` RBAC role or
     * from `positions.title`: the role says who may use the system, and the
     * position title is free text with no reliable meaning. If this is blank,
     * documents print an unnamed signing line rather than inventing a name.
     */
    'principal_name' => env('SCHOOL_PRINCIPAL_NAME', ''),

    'principal_title' => env('SCHOOL_PRINCIPAL_TITLE', 'Kepala Sekolah'),

    // Relative to public/, resolved with asset().
    'logo_path' => env('SCHOOL_LOGO_PATH', 'images/logo.png'),
];
