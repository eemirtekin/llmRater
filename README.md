# LLM Rater

LLM Rater is a Learning Tools Interoperability (LTI) tool designed to automatically evaluate open-ended student responses using Large Language Models (LLMs). It supports both Google's Gemini and OpenAI's GPT models for evaluation.

## Features

- **Multiple LLM Support**: 
  - Google Gemini (default)
  - OpenAI GPT-4
  
- **Question Management**:
  - Create, edit, and delete questions
  - Customizable evaluation criteria using Markdown
  - Additional evaluation instructions per question
  - Attempt limit settings per question

- **Response Handling**:
  - Student response submission
  - Automatic evaluation using LLMs
  - Batch evaluation support
  - Export functionality for responses and evaluations

- **User Interface**:
  - Clean and responsive design
  - Markdown support for questions and evaluation criteria
  - Navigation between responses
  - Progress tracking for students

## System Requirements

- PHP 7.4 or higher
- MySQL/MariaDB database
- Tsugi Framework
- API keys for either:
  - Google Gemini API
  - OpenAI API

## Installation

1. Place the application files in your Tsugi tools directory
2. Configure your database settings in the Tsugi config file
3. Access the tool through your LMS using LTI integration
4. Configure API keys in the tool settings

## Configuration

### API Keys
As an instructor, you need to configure at least one of these API keys:
1. Go to the tool settings
2. Enter your Gemini API key and/or OpenAI API key
3. Save the settings

## Usage

### For Instructors

1. **Creating Questions**:
   - Click "Create Question" in the top menu
   - Fill in the title, question text, and evaluation criteria
   - Set optional parameters like attempt limits
   - Choose the LLM model (Gemini or OpenAI)

2. **Managing Responses**:
   - View all student responses
   - Evaluate responses individually or in batch
   - Export responses and evaluations to CSV

3. **Editing Questions**:
   - Edit question content and settings
   - Update evaluation criteria
   - Delete questions or clear evaluations

### For Students

1. **Viewing Questions**:
   - See available questions in the list
   - View attempt limits and remaining attempts

2. **Submitting Responses**:
   - Select a question
   - Submit your answer
   - View your previous attempts

## File Structure

```
llmRater/
├── css/
│   └── custom.css           # Custom styling
├── functions/
│   ├── auth.php            # Authentication and authorization functions
│   ├── export.php          # Export functionality handlers
│   └── ui.php              # User interface helper functions
├── lib/
│   ├── db.php              # Database operations and table management
│   ├── gemini.php          # Google Gemini API integration
│   ├── openai.php          # OpenAI API integration
│   └── parsedown.php       # Markdown processing (3rd party)
├── modals/
│   ├── question.create.php      # Question creation form modal
│   ├── question.edit.php        # Question editing form modal
│   ├── question.delete.php      # Question deletion confirmation modal
│   ├── evaluations.delete.php   # Evaluation deletion confirmation modal
│   └── rubric.view.php         # Rubric viewing modal
├── export.php              # Export functionality
├── index.php              # Main application file
├── register.php           # LTI registration
├── tsugi.php             # Tsugi framework integration
└── view.php              # Response viewing interface
```

## Security Features

- LTI authentication and authorization
- Role-based access control
- Input validation and sanitization
- Secure API key handling

## Credits

This tool is built on the Tsugi Framework and uses:
- Parsedown for Markdown processing
- Bootstrap for UI components
- Font Awesome for icons

## License

Apache License - See register.php for details