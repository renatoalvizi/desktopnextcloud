# SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
# SPDX-FileCopyrightText: 2012 ownCloud GmbH
# SPDX-License-Identifier: GPL-2.0-or-later
#
# keep the application name and short name the same or different for dev and prod build
# or some migration logic will behave differently for each build
if(NEXTCLOUD_DEV)
    set( APPLICATION_NAME       "Edalvix Cloud Dev" )
    set( APPLICATION_SHORTNAME  "EdalvixCloudDev" )
    set( APPLICATION_EXECUTABLE "edalvixclouddev" )
    set( APPLICATION_ICON_NAME  "EdalvixCloud" )
else()
    set( APPLICATION_NAME       "Edalvix Cloud" )
    set( APPLICATION_SHORTNAME  "EdalvixCloud" )
    set( APPLICATION_EXECUTABLE "edalvixcloud" )
    set( APPLICATION_ICON_NAME  "${APPLICATION_SHORTNAME}" )
endif()

set( APPLICATION_CONFIG_NAME "${APPLICATION_EXECUTABLE}" )
set( APPLICATION_DOMAIN     "edalvix.com.br" )
set( APPLICATION_VENDOR     "Edalvix" )
set( APPLICATION_UPDATE_URL "https://updates.edalvix.cloud/client/" CACHE STRING "URL for updater" )
set( APPLICATION_HELP_URL   "https://edalvix.com.br" CACHE STRING "URL for the help menu" )

# Default macOS builds (Nextcloud + NextcloudDev) use the Icon Composer (.icon)
# format for the app icon. That format can only be compiled by a recent enough
# toolchain (Xcode 26 ships the actool that understands .icon, and macOS 26
# provides the matching SDK), so we gate the modern pipeline on the build
# environment. Older environments — and branded customer builds, which use a
# different APPLICATION_NAME and ship their own colourful icon SVG — fall back
# to the historical Inkscape + ECM (ecm_add_app_icon) .icns pipeline instead.
#
# The Xcode/actool version is the real capability gate; the macOS check is a
# coarse secondary guard. Detection runs every configure and is intentionally
# NOT cached, so the decision self-heals once the build host is upgraded without
# requiring a clean reconfigure. Pass -DMACOS_USE_ICON_COMPOSER=ON/OFF to
# override auto-detection entirely.
if(APPLE)
    execute_process(COMMAND sw_vers -productVersion
        OUTPUT_VARIABLE _macos_product_version RESULT_VARIABLE _macos_ver_result
        OUTPUT_STRIP_TRAILING_WHITESPACE ERROR_QUIET)
    execute_process(COMMAND xcodebuild -version
        OUTPUT_VARIABLE _xcodebuild_version_raw RESULT_VARIABLE _xcode_ver_result
        OUTPUT_STRIP_TRAILING_WHITESPACE ERROR_QUIET)

    set(MACOS_ICON_COMPOSER_TOOLCHAIN_SUPPORTED OFF)
    if(_macos_ver_result EQUAL 0 AND _xcode_ver_result EQUAL 0)
        string(REGEX MATCH "Xcode ([0-9]+\\.[0-9]+)" _ "${_xcodebuild_version_raw}")
        set(_xcode_version "${CMAKE_MATCH_1}")
        message(STATUS "Detected build environment: macOS ${_macos_product_version}, Xcode ${_xcode_version}")
        if(_macos_product_version VERSION_GREATER_EQUAL "26.5"
           AND _xcode_version VERSION_GREATER_EQUAL "26.5")
            set(MACOS_ICON_COMPOSER_TOOLCHAIN_SUPPORTED ON)
        endif()
    endif()

    if(NOT DEFINED MACOS_USE_ICON_COMPOSER)
        if((APPLICATION_NAME STREQUAL "Nextcloud" OR APPLICATION_NAME STREQUAL "NextcloudDev")
           AND EXISTS "${CMAKE_SOURCE_DIR}/theme/colored/AppIcon.icon/icon.json"
           AND MACOS_ICON_COMPOSER_TOOLCHAIN_SUPPORTED)
            set(MACOS_USE_ICON_COMPOSER ON)
            message(STATUS "Using Icon Composer (.icon) format for the macOS app icon.")
        else()
            set(MACOS_USE_ICON_COMPOSER OFF)
        endif()
    endif()

    if(NOT MACOS_USE_ICON_COMPOSER)
        message(STATUS "macOS app icon: using legacy ECM .icns pipeline.")
        # Restore the macOS-specific (squircle) icon for the default builds so the
        # legacy pipeline emits Nextcloud-macOS.icns rather than the generic logo.
        # Branded builds keep their own ${APPLICATION_ICON_NAME}-icon.svg.
        if((APPLICATION_NAME STREQUAL "Nextcloud" OR APPLICATION_NAME STREQUAL "NextcloudDev")
           AND EXISTS "${CMAKE_SOURCE_DIR}/theme/colored/Nextcloud-macOS-icon.svg")
            set(APPLICATION_ICON_NAME "Nextcloud-macOS")
            message(STATUS "Using macOS-specific application icon: ${APPLICATION_ICON_NAME}")
        endif()
    endif()
endif()

set( APPLICATION_ICON_SET   "SVG" )
## Edalvix Cloud: per-customer server pre-configuration
#
# Every Edalvix Cloud customer lives at https://<tenant>.edalvix.cloud.
#
#  - Build a client locked to one customer:
#        cmake -DEDALVIX_TENANT=algumaempresa ...
#    -> the wizard opens with https://algumaempresa.edalvix.cloud and the
#       field cannot be changed.
#  - Build a generic client (EDALVIX_TENANT empty): the user may type only
#    the customer name ("algumaempresa") in the wizard and it is expanded to
#    https://algumaempresa.edalvix.cloud automatically. Full URLs still work.
#  - APPLICATION_SERVER_URL can still be passed directly for any other URL.
set( EDALVIX_CLOUD_DOMAIN "edalvix.cloud" CACHE STRING "Base domain of Edalvix Cloud tenants (https://<tenant>.<domain>)" )
set( EDALVIX_TENANT "" CACHE STRING "Customer subdomain to pre-configure, e.g. 'algumaempresa' -> https://algumaempresa.edalvix.cloud" )
set( APPLICATION_SERVER_URL "" CACHE STRING "URL for the server to use. If entered, the UI field will be pre-filled with it" )
if(EDALVIX_TENANT)
    string(TOLOWER "${EDALVIX_TENANT}" EDALVIX_TENANT_NORMALIZED)
    if(NOT EDALVIX_TENANT_NORMALIZED MATCHES "^[a-z0-9]([a-z0-9-]*[a-z0-9])?$")
        message(FATAL_ERROR "EDALVIX_TENANT='${EDALVIX_TENANT}' is not a valid subdomain (use letters, digits and '-').")
    endif()
    set( APPLICATION_SERVER_URL "https://${EDALVIX_TENANT_NORMALIZED}.${EDALVIX_CLOUD_DOMAIN}" )
    message(STATUS "Edalvix Cloud client pre-configured for ${APPLICATION_SERVER_URL}")
endif()
# Single-label input in the wizard ("algumaempresa") becomes https://algumaempresa.<suffix>
set( APPLICATION_SERVER_DOMAIN_SUFFIX "${EDALVIX_CLOUD_DOMAIN}" )
set( APPLICATION_SERVER_URL_ENFORCE ON ) # If set and APPLICATION_SERVER_URL is defined, the server can only connect to the pre-defined URL
set( APPLICATION_REV_DOMAIN "br.com.edalvix.cloud" )
set( APPLICATION_REV_DOMAIN_DBUS "cloud.edalvix.com.br" )
set( DEVELOPMENT_TEAM "" CACHE STRING "Apple Development Team ID (set to the Edalvix Apple team for signed macOS builds)" )
set( APPLICATION_VIRTUALFILE_SUFFIX "edalvix" CACHE STRING "Virtual file suffix (not including the .)")
set( APPLICATION_OCSP_STAPLING_ENABLED OFF )
set( APPLICATION_FORBID_BAD_SSL OFF )

set( LINUX_PACKAGE_SHORTNAME "edalvixcloud" )
set( LINUX_APPLICATION_ID "${APPLICATION_REV_DOMAIN}.${LINUX_PACKAGE_SHORTNAME}")
if(LINUX)
    set( APPLICATION_ICON_NAME "${LINUX_APPLICATION_ID}" )
endif()

set( THEME_CLASS            "NextcloudTheme" )
set( WIN_SETUP_BITMAP_PATH  "${CMAKE_SOURCE_DIR}/admin/win/nsi" )

set( MAC_INSTALLER_BACKGROUND_FILE "${CMAKE_SOURCE_DIR}/admin/osx/installer-background.png" CACHE STRING "The MacOSX installer background image")

# set( THEME_INCLUDE          "${OEM_THEME_DIR}/mytheme.h" )
# set( APPLICATION_LICENSE    "${OEM_THEME_DIR}/license.txt )

## Updater options
option( BUILD_UPDATER "Build updater (update server: admin/edalvix/update-server)" ON )
# macOS (Sparkle): EdDSA public key of the EDALVIX update-signing key pair.
# Generate once with Sparkle's `generate_keys` and keep the private key secret;
# sign each release with `sign_update`. Without it macOS builds cannot self-update.
set( SPARKLE_PUBLIC_ED_KEY "" CACHE STRING "Sparkle EdDSA public key used to verify macOS updates" )
if(APPLE AND BUILD_UPDATER AND NOT SPARKLE_PUBLIC_ED_KEY)
    message(WARNING "SPARKLE_PUBLIC_ED_KEY is empty: macOS automatic updates will be rejected by Sparkle.")
endif()

option( WITH_PROVIDERS "Build with providers list" OFF )

option( ENFORCE_VIRTUAL_FILES_SYNC_FOLDER "Enforce use of virtual files sync folder when available" OFF )
option( DISABLE_VIRTUAL_FILES_SYNC_FOLDER "Disable use of virtual files sync folder even when available" OFF )

option(ENFORCE_SINGLE_ACCOUNT "Enforce use of a single account in desktop client" OFF)

option( DO_NOT_USE_PROXY "Do not use system wide proxy, instead always do a direct connection to server" OFF )

option( WIN_DISABLE_USERNAME_PREFILL "Do not prefill the Windows user name when creating a new account" OFF )

## Interface language: Edalvix Cloud always starts in Brazilian Portuguese,
## whatever the OS language. Users can still switch with --set-language <code>;
## set to "" to follow the OS language again.
set( APPLICATION_DEFAULT_LANGUAGE "pt_BR" CACHE STRING "Default interface language (empty = OS language)" )

## Theming options
set(NEXTCLOUD_BACKGROUND_COLOR "#0068ce" CACHE STRING "Default background color (Edalvix Cloud blue)")
set( APPLICATION_WIZARD_HEADER_BACKGROUND_COLOR "#ffffff" CACHE STRING "Hex color of the wizard header background")
set( APPLICATION_WIZARD_HEADER_TITLE_COLOR "#071d33" CACHE STRING "Hex color of the text in the wizard header")
option( APPLICATION_WIZARD_USE_CUSTOM_LOGO "Use the logo from ':/client/theme/colored/wizard_logo.(png|svg)' else the default application icon is used" ON )

#
## Windows Shell Extensions & MSI - IMPORTANT: Generate new GUIDs for custom builds with "guidgen" or "uuidgen"
#
if(WIN32)
    # Context Menu
    set( WIN_SHELLEXT_CONTEXT_MENU_GUID      "{9392DCA4-4C2E-4D5F-BE60-AC6F810F35EA}" )

    # Overlays
    set( WIN_SHELLEXT_OVERLAY_GUID_ERROR     "{0B5B0CDF-4858-41AC-9FFB-9CBC8B50A68F}" )
    set( WIN_SHELLEXT_OVERLAY_GUID_OK        "{F27170F8-7B46-4F7D-A765-84F12994B965}" )
    set( WIN_SHELLEXT_OVERLAY_GUID_OK_SHARED "{4DEDE8EF-D254-4AC9-8703-E065C14D8111}" )
    set( WIN_SHELLEXT_OVERLAY_GUID_SYNC      "{7437B849-85CB-46E4-A524-00157845BEB1}" )
    set( WIN_SHELLEXT_OVERLAY_GUID_WARNING   "{881BC3F5-08F7-44F6-80EC-D8512C495097}" )

    # MSI Upgrade Code (without brackets)
    set( WIN_MSI_UPGRADE_CODE                "910EC754-95E5-46AF-B51E-A5CAB0D61CDF" )

    # Windows build options
    option( BUILD_WIN_MSI "Build MSI scripts and helper DLL" OFF )
    option( BUILD_WIN_TOOLS "Build Win32 migration tools" OFF )
endif()

if (APPLE AND CMAKE_OSX_DEPLOYMENT_TARGET VERSION_GREATER_EQUAL 11.0)
    option( BUILD_FILE_PROVIDER_MODULE "Build the macOS virtual files File Provider module" OFF )
endif()
