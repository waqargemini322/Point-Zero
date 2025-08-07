#!/bin/bash

# Create Plugin ZIP Files Script
# This script creates downloadable ZIP files for all optimized plugins

echo "🚀 Creating ZIP files for all optimized plugins..."

# Create output directory
mkdir -p /workspace/plugin_zips

# Function to create zip with proper structure
create_plugin_zip() {
    local plugin_name=$1
    local plugin_path="/workspace/plugins/$plugin_name"
    local zip_path="/workspace/plugin_zips/$plugin_name.zip"
    
    if [ -d "$plugin_path" ]; then
        echo "📦 Creating ZIP for: $plugin_name"
        cd /workspace/plugins
        zip -r "$zip_path" "$plugin_name" -x "*.git*" "*.DS_Store*" "*Thumbs.db*"
        echo "✅ Created: $zip_path"
    else
        echo "❌ Plugin directory not found: $plugin_path"
    fi
}

# Create ZIP files for all optimized plugins
echo ""
echo "Creating ZIP files..."
echo "===================="

create_plugin_zip "custom-membership-manager"
create_plugin_zip "ProjectTheme_livechat" 
create_plugin_zip "projecttheme_ewallet"
create_plugin_zip "projecttheme_service_offering"

echo ""
echo "🎉 All ZIP files created successfully!"
echo ""
echo "📁 ZIP files location: /workspace/plugin_zips/"
echo ""
echo "📋 Created files:"
ls -la /workspace/plugin_zips/ | grep ".zip"

echo ""
echo "💡 To download these files:"
echo "1. Copy the files from /workspace/plugin_zips/ to your local machine"
echo "2. Or use the terminal commands below to download each file"
echo ""
echo "📥 Download commands:"
echo "====================="
echo "# Custom Membership Manager (Main system)"
echo "curl -O file:///workspace/plugin_zips/custom-membership-manager.zip"
echo ""
echo "# Live Chat System" 
echo "curl -O file:///workspace/plugin_zips/ProjectTheme_livechat.zip"
echo ""
echo "# E-Wallet System"
echo "curl -O file:///workspace/plugin_zips/projecttheme_ewallet.zip"
echo ""
echo "# Service Offering (Fixed)"
echo "curl -O file:///workspace/plugin_zips/projecttheme_service_offering.zip"
echo ""
echo "🔗 You can also create a GitHub repository and push these files there."