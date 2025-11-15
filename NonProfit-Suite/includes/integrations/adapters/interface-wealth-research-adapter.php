<?php
/**
 * Wealth Research Adapter Interface
 *
 * Defines the contract for all wealth research provider integrations.
 * Supports donor intelligence, capacity rating, and philanthropic history research.
 *
 * @package NonprofitSuite
 * @subpackage Integrations/Adapters
 * @since 1.18.0
 */

namespace NonprofitSuite\Integrations\Adapters;

interface NS_Wealth_Research_Adapter {

	/**
	 * Screen individual for basic wealth indicators
	 *
	 * Quick screening to get basic wealth and giving capacity information.
	 *
	 * @param array $params {
	 *     Individual information for screening.
	 *
	 *     @type string $first_name   First name (required)
	 *     @type string $last_name    Last name (required)
	 *     @type string $email        Email address (optional)
	 *     @type string $phone        Phone number (optional)
	 *     @type string $address      Street address (optional)
	 *     @type string $city         City (optional)
	 *     @type string $state        State (optional)
	 *     @type string $zip          ZIP code (optional)
	 *     @type string $company      Company name (optional)
	 * }
	 * @return array {
	 *     Screening results.
	 *
	 *     @type bool   $success              Whether screening succeeded
	 *     @type string $individual_id        Provider's unique ID for this individual
	 *     @type string $giving_capacity      Capacity rating (A+, A, B, C, D)
	 *     @type string $income_range         Estimated income range
	 *     @type string $net_worth_range      Estimated net worth range
	 *     @type int    $confidence_score     Confidence in data (0-100)
	 *     @type float  $cost                 Cost of this screening
	 *     @type string $error_message        Error message if failed
	 * }
	 */
	public function screen_individual( $params );

	/**
	 * Get full profile for an individual
	 *
	 * Comprehensive profile including wealth indicators, biographical data,
	 * and philanthropic history.
	 *
	 * @param array $params Individual identification parameters (same as screen_individual)
	 * @return array {
	 *     Full profile data.
	 *
	 *     @type bool   $success                    Whether request succeeded
	 *     @type string $individual_id              Provider's unique ID
	 *     @type array  $wealth_indicators {
	 *         @type string $income_range           Estimated income range
	 *         @type string $net_worth_range        Net worth range
	 *         @type float  $real_estate_value      Total real estate value
	 *         @type array  $business_affiliations  Business connections
	 *         @type array  $stock_holdings         Public stock holdings
	 *     }
	 *     @type array  $biographical {
	 *         @type string $age_range              Age range
	 *         @type array  $education              Education history
	 *         @type array  $professional           Career background
	 *     }
	 *     @type array  $social {
	 *         @type array  $social_media           Social media profiles
	 *         @type array  $interests              Interests and hobbies
	 *     }
	 *     @type float  $cost                       Cost of this profile
	 *     @type string $error_message              Error if failed
	 * }
	 */
	public function get_profile( $params );

	/**
	 * Get giving capacity rating
	 *
	 * Determines an individual's capacity to give to charitable organizations.
	 *
	 * @param array $params Individual identification parameters
	 * @return array {
	 *     Capacity rating results.
	 *
	 *     @type bool   $success               Whether rating succeeded
	 *     @type string $capacity_rating       Rating (A+, A, B, C, D)
	 *     @type string $capacity_range        Estimated giving capacity range
	 *     @type array  $rating_factors        Factors contributing to rating
	 *     @type int    $confidence_score      Confidence (0-100)
	 *     @type float  $cost                  Cost of rating
	 *     @type string $error_message         Error if failed
	 * }
	 */
	public function get_capacity_rating( $params );

	/**
	 * Get philanthropic history
	 *
	 * Research past charitable donations, board memberships, and political contributions.
	 *
	 * @param array $params Individual identification parameters
	 * @return array {
	 *     Philanthropic history.
	 *
	 *     @type bool   $success                  Whether request succeeded
	 *     @type array  $donations {
	 *         Array of donation records.
	 *         @type string $organization         Organization name
	 *         @type float  $amount               Donation amount (if known)
	 *         @type string $date                 Date of donation
	 *         @type string $type                 Type of donation
	 *     }
	 *     @type array  $board_affiliations       Nonprofit board memberships
	 *     @type float  $political_contributions  Total political contributions
	 *     @type array  $political_parties        Political party affiliations
	 *     @type float  $estimated_lifetime       Estimated lifetime giving
	 *     @type float  $cost                     Cost of research
	 *     @type string $error_message            Error if failed
	 * }
	 */
	public function get_philanthropic_history( $params );

	/**
	 * Get real estate holdings
	 *
	 * Research property ownership and real estate investments.
	 *
	 * @param array $params Individual identification parameters
	 * @return array {
	 *     Real estate data.
	 *
	 *     @type bool   $success             Whether request succeeded
	 *     @type array  $properties {
	 *         Array of property records.
	 *         @type string $address         Property address
	 *         @type float  $assessed_value  Assessed value
	 *         @type string $property_type   Type (residential, commercial, etc)
	 *         @type int    $year_purchased  Year of purchase
	 *     }
	 *     @type float  $total_value         Total real estate value
	 *     @type int    $property_count      Number of properties
	 *     @type float  $cost                Cost of research
	 *     @type string $error_message       Error if failed
	 * }
	 */
	public function get_real_estate_holdings( $params );

	/**
	 * Get business affiliations
	 *
	 * Research business connections, executive positions, and company ownership.
	 *
	 * @param array $params Individual identification parameters
	 * @return array {
	 *     Business affiliation data.
	 *
	 *     @type bool   $success              Whether request succeeded
	 *     @type array  $affiliations {
	 *         Array of business affiliations.
	 *         @type string $company          Company name
	 *         @type string $position         Position/title
	 *         @type string $start_date       Start date
	 *         @type bool   $is_current       Whether currently active
	 *         @type string $company_type     Type of company
	 *         @type float  $company_revenue  Company revenue (if public)
	 *     }
	 *     @type float  $cost                 Cost of research
	 *     @type string $error_message        Error if failed
	 * }
	 */
	public function get_business_affiliations( $params );

	/**
	 * Search by email address
	 *
	 * Look up individual information using email address.
	 *
	 * @param string $email Email address to search
	 * @return array {
	 *     Search results.
	 *
	 *     @type bool   $success          Whether search succeeded
	 *     @type bool   $found            Whether individual was found
	 *     @type string $individual_id    Provider's unique ID (if found)
	 *     @type array  $basic_info       Basic information (name, location, etc)
	 *     @type float  $cost             Cost of search
	 *     @type string $error_message    Error if failed
	 * }
	 */
	public function search_by_email( $email );

	/**
	 * Search by name and location
	 *
	 * Look up individuals by name with optional location filters.
	 *
	 * @param string $first_name First name
	 * @param string $last_name  Last name
	 * @param array  $location {
	 *     Optional location filters.
	 *
	 *     @type string $city   City
	 *     @type string $state  State
	 *     @type string $zip    ZIP code
	 * }
	 * @return array {
	 *     Search results.
	 *
	 *     @type bool   $success           Whether search succeeded
	 *     @type array  $matches {
	 *         Array of potential matches.
	 *         @type string $individual_id Provider's unique ID
	 *         @type string $first_name    First name
	 *         @type string $last_name     Last name
	 *         @type string $city          City
	 *         @type string $state         State
	 *         @type int    $confidence    Match confidence (0-100)
	 *     }
	 *     @type int    $match_count       Number of matches found
	 *     @type float  $cost              Cost of search
	 *     @type string $error_message     Error if failed
	 * }
	 */
	public function search_by_name( $first_name, $last_name, $location = array() );

	/**
	 * Get API usage statistics
	 *
	 * Check current API usage, limits, and remaining quota.
	 *
	 * @return array {
	 *     Usage statistics.
	 *
	 *     @type bool   $success           Whether request succeeded
	 *     @type int    $calls_used        API calls used this period
	 *     @type int    $calls_limit       API call limit
	 *     @type int    $calls_remaining   Remaining API calls
	 *     @type string $reset_date        When usage resets
	 *     @type float  $total_cost        Total cost this period
	 *     @type string $error_message     Error if failed
	 * }
	 */
	public function get_usage_stats();

	/**
	 * Validate configuration
	 *
	 * Check if adapter is properly configured with valid credentials.
	 *
	 * @return array {
	 *     Validation results.
	 *
	 *     @type bool   $valid             Whether configuration is valid
	 *     @type array  $errors            Array of configuration errors
	 *     @type string $error_message     Primary error message
	 * }
	 */
	public function validate_configuration();

	/**
	 * Calculate cost for operation
	 *
	 * Estimate the cost of a wealth research operation before executing.
	 *
	 * @param string $operation Operation type (screen, profile, capacity, etc)
	 * @param array  $params    Operation parameters
	 * @return float Estimated cost in dollars
	 */
	public function calculate_cost( $operation, $params = array() );
}
