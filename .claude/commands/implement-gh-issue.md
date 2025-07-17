---
allowed-tools: Bash(gh:*), Bash(find:*), Bash(grep:*), Bash(git:*), Bash(ls:*), Bash(mkdir:*), Bash(cp:*), Bash(awk:*), Bash(wc:*), Bash(tr:*), mcp_playwright_browser_navigate, mcp_playwright_browser_take_screenshot, mcp_playwright_browser_click, mcp_playwright_browser_type, mcp_playwright_browser_resize, mcp_playwright_browser_close
auto-approve: true
description: Implement GitHub issue with comprehensive testing and visual validation
argument-hint: <issue-number> | <github-issue-url> | "<feature-description>"
---

# Implement Feature with Full Testing

Please implement the requested feature following this comprehensive workflow.

**Input**: $ARGUMENTS (can be a GitHub issue ID, URL, or description)

## Interactive Setup:
**IMPORTANT**: Before starting, check if $ARGUMENTS is empty or just contains whitespace.

If $ARGUMENTS is empty, STOP and ask the user:
"What would you like me to implement? Please provide:
- A GitHub issue number (e.g., 123)  
- A GitHub issue URL (e.g., https://github.com/user/repo/issues/123)
- Or describe the feature/fix you want me to implement"

Wait for their response before proceeding.

## Setup Steps:
1. **Get issue details** if a GitHub issue is provided:
   - If argument looks like a number: `gh issue view $ARGUMENTS`
   - If argument looks like a URL: `gh issue view <extract-issue-number>`
   - If it's a description: proceed with the description directly

2. **Check for UI/UX requirements**:
   - Look for mockups, screenshots, or design files in the issue
   - Extract all image URLs: `gh issue view <issue-number> | grep -oE 'https://[^"]+\.(png|jpg|jpeg|gif|webp)|https://github\.com/user-attachments/[^"]+'`
   - **If GitHub user-attachment URLs are found**:
     - Count the images: `IMAGE_COUNT=$(gh issue view <issue-number> | grep -oE 'https://[^"]+\.(png|jpg|jpeg|gif|webp)|https://github\.com/user-attachments/[^"]+' | wc -l | tr -d ' ')`
     - STOP and inform: "I found $IMAGE_COUNT image attachment(s) in this issue that I cannot access directly:"
     - List numbered URLs:
       ```
       gh issue view <issue-number> | grep -oE 'https://[^"]+\.(png|jpg|jpeg|gif|webp)|https://github\.com/user-attachments/[^"]+' | awk '{print NR ". " $0}'
       ```
     - Ask: "Please provide for each numbered image either:
       - The local file path after downloading
       - The direct AWS URL from your browser
       - A description of what the image shows
       
       Example response: 'Image 1: /tmp/mockup.png, Image 2: Shows the loading skeleton'"
     - Wait for their response before continuing
   - Note any visual requirements or design specifications

## Implementation Steps:
3. **Understand the requirements** from the issue/description
4. **Search for existing related code** using `find` and `grep`
5. **Implement the feature/fix** in the appropriate files

## Browser Testing Setup:
6. **Initialize Playwright browser for visual testing**:
   - Open browser: `mcp_playwright_browser_navigate` to your local WordPress admin
   - **Login manually when prompted** - the browser will pause for you to complete login
   - Navigate to the relevant admin page for testing

## Visual Testing Steps:
7. **For UI/UX changes, capture before/after screenshots**:
   - Take "before" screenshot: `mcp_playwright_browser_take_screenshot` with filename like `tmp/screenshots/before-feature-name.jpg`
   - Implement your changes
   - Take "after" screenshot: `mcp_playwright_browser_take_screenshot` with filename like `tmp/screenshots/after-feature-name.jpg`
   
8. **If mockup was provided for comparison**:
   - Save mockup locally: `mkdir -p tmp/mockups && cp <mockup_path> tmp/mockups/`
   - Compare visually between mockup and your screenshot
   - Iterate on implementation until visual requirements are met
   - Take final screenshot when satisfied

9. **Test interactive elements** (if applicable):
   - Use `mcp_playwright_browser_click`, `mcp_playwright_browser_type`, etc. to test functionality
   - Take screenshots of different states (hover, active, error states) using `mcp_playwright_browser_take_screenshot`
   - Verify responsive behavior by resizing: `mcp_playwright_browser_resize`

## Completion:
10. **Take final documentation screenshots**:
    - Screenshot the completed feature: `mcp_playwright_browser_take_screenshot` with descriptive filename
    - Close browser: `mcp_playwright_browser_close`

11. **Review all screenshots**: `ls -la tmp/screenshots/` to see generated files

12. **Confirm the feature works** as intended

13. **Create descriptive commit message** referencing the issue if applicable

14. **Document any breaking changes** or migration requirements

## Notes:
- The browser will remain open between screenshots, so you only need to log in once
- Use descriptive filenames for screenshots (e.g., `admin-dashboard-new-widget.jpg`)
- Take screenshots at key interaction points to document the user experience
- The browser viewport is set to 1280x800 by default in the Playwright config