# FreeScout GitHub Integration Module

## Project Overview

This module integrates FreeScout (help desk system) with GitHub to enable support teams to create, link, and track GitHub issues directly from support conversations. The module uses AI to generate issue content based on conversation threads and provides bidirectional synchronization between FreeScout and GitHub.

## Module Structure Review (2025-07-24)

### Current Status
The module follows FreeScout's modular structure correctly with the following observations:

#### ✅ Properly Implemented:
1. **Module Configuration**: 
   - `module.json` correctly defines the module with proper namespace and service provider
   - `composer.json` has correct PSR-4 autoloading configuration
   - `start.php` follows the standard pattern

2. **Service Provider**: 
   - `GithubServiceProvider.php` properly extends Laravel's ServiceProvider
   - Correctly registers hooks using `\Eventy` for integration points
   - Implements settings management, asset loading, and sidebar integration

3. **Database Structure**:
   - Migration files are properly structured
   - Tables follow naming conventions (`github_issues`, `github_issue_conversation`, `github_label_mappings`)

4. **Directory Structure**:
   - Follows Laravel/FreeScout module conventions
   - All required directories are present (Config, Database, Entities, Http, Providers, Resources, Services)

#### ⚠️ Issues Found:

1. **Missing Entity Class**: 
   - The `GithubIssueConversation` entity class is missing despite having a migration for the `github_issue_conversation` table
   - This entity is needed for proper relationship management between issues and conversations

2. **Module Alias Constant**:
   - The Jira module defines a module alias constant (`define('JIRA_MODULE', 'jira')`) in the ServiceProvider
   - The GitHub module should follow this pattern for consistency

3. **Missing View Files**:
   - The `ajax_html` directory is empty - needs modal views for issue creation/linking
   - Based on the Jira module, we need at least `link_issue.blade.php`

4. **Service Provider Differences**:
   - Jira module includes helper constants for API methods and configuration
   - Jira module has static properties for caching user/meta data
   - Consider adding similar patterns for consistency

## Key Features

### Core Functionality
- **AI-Powered Issue Creation**: Generate GitHub issue titles and descriptions using AI analysis of support conversation threads
- **Intelligent Label Assignment**: Automatically assign GitHub labels based on FreeScout conversation tags or AI analysis of conversation content
- **Issue Linking**: Link existing GitHub issues to FreeScout conversations
- **Sidebar Integration**: Display related GitHub issues in conversation sidebar with status indicators
- **Bidirectional Sync**: Receive GitHub webhook notifications to update FreeScout when issue status changes
- **Conversation Status Sync**: Automatically update FreeScout conversation status when linked GitHub issues are closed

### User Experience
- **Modal-based UI**: Create/link issues via modal dialogs without leaving the conversation
- **Real-time Search**: Search existing GitHub issues with autocomplete
- **Visual Status Indicators**: Show issue status, labels, and assignees in sidebar
- **Automatic Notes**: Add system notes to conversations when issues are created or status changes

## Technical Architecture

### Based on FreeScout Jira Module Patterns
- **Laravel Modular Structure**: Service provider, entities, controllers, views
- **Event-Driven Integration**: Uses `\Eventy` hooks for FreeScout integration
- **AJAX-based Frontend**: Progressive enhancement with graceful degradation
- **Webhook Support**: GitHub webhooks for real-time status updates

### Database Schema
```sql
-- Main GitHub issues table (cached data)
github_issues:
- id (primary key)
- number (GitHub issue number)
- repository (owner/repo format)
- title (cached from GitHub)
- state (open/closed)
- labels (JSON array)
- assignees (JSON array)
- created_at, updated_at

-- Junction table for issue-conversation relationships
github_issue_conversation:
- id (primary key)
- github_issue_id (foreign key)
- conversation_id (foreign key)
- unique constraint on (github_issue_id, conversation_id)
```

## Implementation Progress (2025-07-25)

### ✅ Completed Implementation:

#### Phase 1: Module Foundation
1. **Module Structure Setup** ✓
   - Laravel modular structure created following FreeScout patterns
   - Service provider, configuration, and routing all properly implemented
   - Database migrations for entities created and functional
   - All required module files (module.json, composer.json, start.php) properly configured

2. **GitHub API Integration** ✓
   - Comprehensive GitHub API client implemented with authentication
   - Full CRUD operations for issues (create, read, update, search)
   - Advanced repository discovery (personal, organization, and installation repos)
   - Rate limiting and error handling implemented
   - Support for both classic and fine-grained personal access tokens

3. **Basic UI Components** ✓
   - Settings page with GitHub configuration completed
   - Test connection with detailed feedback (user info, permissions, rate limits)
   - Repository dropdown with smart caching and loading indicators
   - Sidebar component displaying linked issues with status badges

#### Phase 2: Core Features
4. **Issue Management** ✓
   - Create issue functionality with modal dialog
   - Link existing issues with search functionality
   - Unlink issues with confirmation
   - Issue search with autocomplete and real-time results
   - System notes added to conversations for all issue operations

5. **AI Content Generation** ✓
   - IssueContentGenerator service implemented
   - Support for OpenAI and Claude APIs
   - Manual fallback when AI is unavailable
   - Generate button in create issue modal
   - Auto-generate checkbox option

6. **Sidebar Enhancement** ✓
   - Display issue details (status, labels, assignees)
   - Action buttons (view on GitHub, unlink, refresh)
   - Real-time status indicators (open/closed)
   - Modal triggers for create/link operations

#### Phase 3: Advanced Features
7. **Webhook System** ✓
   - GitHub webhook endpoint implemented
   - Signature verification for security
   - Issue status change handling
   - Automatic conversation status synchronization

8. **Status Mapping** ✓
   - Automatic conversation closure when GitHub issue is closed
   - System notes for all status changes
   - Bidirectional sync capability

9. **Label Assignment** ✓
   - LabelAssignmentService implemented
   - FreeScout tag to GitHub label mapping
   - AI-powered label suggestion
   - Configurable confidence thresholds
   - Label mapping management UI in settings

### 🎯 Current Status Summary:

The GitHub integration module is **fully implemented** with all planned features operational:

- ✅ **Core Functionality**: All CRUD operations for GitHub issues
- ✅ **AI Integration**: Content generation and label assignment
- ✅ **UI/UX**: Modals, sidebar, settings page all functional
- ✅ **Real-time Sync**: Webhooks and status synchronization working
- ✅ **Security**: Token validation, webhook signatures, permission checks
- ✅ **Performance**: Smart caching, efficient API calls, debounced searches

### 🐛 Known Issues & Limitations:

1. **FreeScout Tag Integration**: The conversation tagging depends on FreeScout's implementation (may need adjustment based on specific FreeScout version)
2. **AI Service Configuration**: Requires manual API key setup in settings
3. **Rate Limiting**: GitHub API rate limits apply (5000 requests/hour for authenticated requests)

### 🚀 Future Enhancement Opportunities:

1. **Pull Request Integration**: Link and track PRs similar to issues
2. **Multi-Repository Support**: Allow issues from multiple repositories per conversation
3. **Advanced Analytics**: Issue resolution metrics and reporting
4. **Custom Fields**: Map FreeScout custom fields to GitHub labels
5. **Batch Operations**: Create/link multiple issues at once

### 📝 Configuration Checklist:

To use the module, ensure:
- [ ] GitHub personal access token configured in settings
- [ ] Default repository selected
- [ ] AI service configured (optional, for content generation)
- [ ] Webhook URL added to GitHub repository settings (optional, for real-time sync)
- [ ] Label mappings configured (optional, for automatic labeling)

## Implementation Plan

### Phase 1: Module Foundation
1. **Module Structure Setup**
   - Create Laravel modular structure following FreeScout patterns
   - Set up service provider, configuration, and basic routing
   - Create database migrations for entities

2. **GitHub API Integration**
   - Implement GitHub API client with authentication
   - Create methods for issue CRUD operations
   - Handle rate limiting and error responses

3. **Basic UI Components**
   - Create settings page for GitHub configuration
   - Implement sidebar component for displaying issues
   - Set up basic modal dialogs for issue operations

### Phase 2: Core Features
4. **Issue Management**
   - Implement issue creation functionality
   - Add issue linking capabilities
   - Create issue search with autocomplete

5. **AI Content Generation**
   - Integrate with AI service (OpenAI/Claude) for content generation
   - Analyze conversation threads to generate issue titles/descriptions
   - Implement intelligent label assignment based on conversation tags or content analysis
   - Provide user editing capabilities before creation

6. **Sidebar Enhancement**
   - Display issue details (status, labels, assignees)
   - Add action buttons (view, unlink, create new)
   - Implement real-time status updates

### Phase 3: Advanced Features
7. **Webhook System**
   - Set up GitHub webhook endpoints
   - Handle issue status change notifications
   - Implement conversation status synchronization

8. **Status Mapping**
   - Create configurable mappings between GitHub and FreeScout statuses
   - Handle automatic conversation state changes
   - Add system notes for status updates

9. **Testing & Polish**
   - Comprehensive testing of all features
   - Error handling and user feedback
   - Performance optimization

## Configuration Requirements

### GitHub Settings
- **GitHub Token**: Personal access token or GitHub App credentials
- **Repository Access**: Read/write permissions for target repositories
- **Webhook URL**: Endpoint for receiving GitHub notifications

### FreeScout Settings
- **AI Service**: OpenAI/Claude API configuration
- **Status Mappings**: GitHub state to FreeScout status mappings
- **Default Repository**: Primary repository for issue creation

## File Structure

```
Github/
├── Config/
│   └── config.php
├── Database/
│   ├── Migrations/
│   │   ├── create_github_issues_table.php
│   │   └── create_github_issue_conversation_table.php
│   └── Seeders/
├── Entities/
│   ├── GithubIssue.php
│   └── GithubIssueConversation.php
├── Http/
│   ├── Controllers/
│   │   └── GithubController.php
│   └── routes.php
├── Providers/
│   └── GithubServiceProvider.php
├── Public/
│   ├── css/
│   │   └── module.css
│   └── js/
│       └── module.js
├── Resources/
│   └── views/
│       ├── settings.blade.php
│       ├── partials/
│       │   └── sidebar.blade.php
│       └── ajax_html/
│           ├── create_issue.blade.php
│           └── link_issue.blade.php
├── Services/
│   ├── GithubApiClient.php
│   ├── IssueContentGenerator.php
│   └── LabelAssignmentService.php
├── composer.json
├── module.json
└── start.php
```

## API Integration Details

### GitHub API Endpoints
- **Issues**: `/repos/{owner}/{repo}/issues`
- **Search**: `/search/issues`
- **Labels**: `/repos/{owner}/{repo}/labels`
- **Webhooks**: `/repos/{owner}/{repo}/hooks`
- **Comments**: `/repos/{owner}/{repo}/issues/{issue_number}/comments`

### Webhook Events
- `issues` - Issue creation, updates, state changes
- `issue_comment` - New comments on issues
- `pull_request` - If supporting PR integration later

## Development Notes

### Testing Strategy
- Unit tests for API client and content generation
- Integration tests for webhook handling
- UI tests for modal interactions
- Manual testing with real GitHub repositories

### Security Considerations
- Secure storage of GitHub tokens
- Webhook signature verification
- Input sanitization for AI-generated content
- Rate limiting for API calls

### Performance Optimization
- Caching of GitHub issue data
- Async webhook processing
- Efficient database queries for sidebar loading
- Minimal API calls through smart caching

## Future Enhancements

### Planned Features
- **Pull Request Integration**: Link and track PRs similar to issues
- **Branch Management**: Create feature branches from conversations
- **Advanced AI Features**: Auto-categorization, priority estimation, smart assignee suggestions
- **Multi-Repository Support**: Handle multiple GitHub repositories
- **Advanced Analytics**: Issue resolution metrics and reporting
- **Custom Label Mapping**: Allow manual mapping of FreeScout tags to GitHub labels

### Integration Opportunities
- **CI/CD Integration**: Trigger builds when issues are created
- **Slack/Teams Notifications**: Cross-platform notifications
- **Time Tracking**: Integrate with time tracking tools
- **Custom Fields**: Map FreeScout custom fields to GitHub labels

## Success Metrics

### Technical Metrics
- API response times < 2 seconds
- Webhook processing latency < 5 seconds
- 99.9% uptime for sync operations
- Zero data loss in bidirectional sync

### User Experience Metrics
- Issue creation time reduced by 70%
- Conversation-to-issue conversion rate
- User adoption rate across support team
- Reduction in manual status updates

---

## AI Label Assignment Feature

### Label Assignment Logic
The system will intelligently assign GitHub labels to issues using a two-tier approach:

#### Primary Method: FreeScout Tag Mapping
- **Direct Tag Mapping**: If the FreeScout conversation has tags that match available GitHub labels, use those directly
- **Fuzzy Matching**: Use string similarity algorithms to match similar tags (e.g., "bug-report" → "bug", "feature-request" → "enhancement")
- **Custom Mapping**: Allow administrators to configure custom mappings between FreeScout tags and GitHub labels

#### Secondary Method: AI Content Analysis
When no suitable tag mapping exists, analyze conversation content to suggest appropriate labels:
- **Content Classification**: Analyze conversation text to identify issue types (bug, feature request, documentation, etc.)
- **Severity Detection**: Identify urgency indicators in conversation to assign priority labels
- **Component Detection**: Analyze technical terms to assign component-specific labels
- **Sentiment Analysis**: Detect customer sentiment to assign appropriate priority or type labels

### Implementation Details

#### Label Assignment Service (`LabelAssignmentService.php`)
```php
class LabelAssignmentService {
    public function assignLabels($conversation, $availableLabels) {
        // 1. Try FreeScout tag mapping first
        $mappedLabels = $this->mapFreeScoutTags($conversation->tags, $availableLabels);
        
        // 2. If no tags or insufficient mapping, use AI analysis
        if (empty($mappedLabels)) {
            $mappedLabels = $this->analyzeConversationContent($conversation, $availableLabels);
        }
        
        // 3. Apply business rules and validation
        return $this->validateAndFilterLabels($mappedLabels, $availableLabels);
    }
}
```

#### Configuration Options
- **Label Mapping Rules**: Admin interface to set up tag → label mappings
- **AI Analysis Toggle**: Enable/disable AI-based label assignment
- **Default Labels**: Set default labels for specific conversation types
- **Label Confidence Threshold**: Minimum confidence score for AI-suggested labels

### Database Schema Addition
```sql
-- Add label mapping configuration
github_label_mappings:
- id (primary key)
- freescout_tag (varchar)
- github_label (varchar)
- repository (varchar)
- confidence_threshold (decimal)
- created_at, updated_at
```

This implementation plan provides a comprehensive roadmap for building a robust GitHub integration that enhances support team productivity while maintaining seamless user experience within FreeScout.