@local_tutores @javascript
Feature: local_tutores test harness smoke check
  Confirm the Behat pipeline boots, logs in and renders a page.
  Replace this with real acceptance scenarios for the plugin.

  Scenario: Admin can log in and reach the front page
    Given I log in as "admin"
    # "Acceptance test site" is the site fullname set by Behat init, so this
    # assertion is independent of the Moodle version and the UI language.
    Then I should see "Acceptance test site"
