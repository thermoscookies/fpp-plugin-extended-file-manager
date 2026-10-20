#!/bin/bash
set -e

# Extended File Manager install script

# Include common scripts functions and variables
. ${FPPDIR}/scripts/common

# Add required Apache CSP (Content-Security-Policy allowed domains
# Possible Keys are: 'default-src', 'connect-src', 'img-src', 'script-src', 'style-src', 'object-src', 'font-src'
# (an iframe or media load is governed by 'default-src'). Every domain added here receives the
# user's browser address on each page view, so it must be a `sends` entry in pluginInfo.json's
# privacy block (PLUGIN_GUIDELINES.md 14.1)
# Examples:
# ${FPPDIR}/scripts/ManageApacheContentPolicy.sh add connect-src https://domaintotrust.co.uk
# ${FPPDIR}/scripts/ManageApacheContentPolicy.sh add img-src https://anotherdomain.com

# This plugin has no commands, services, packages, or external files to set up.
# Its PHP page and API are loaded by FPP's plugin manager.

