USE copy_trading;

-- Version 1
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (1, 0, NOW(), NULL);

-- MyFXTM Questions
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES ( 1, 1,  1, NULL, 'Date of Birth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES ( 2, 1,  2, NULL, 'Occupation');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES ( 3, 1,  3, NULL, 'Level of Education');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES ( 4, 1,  4, NULL, 'Annual Income');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES ( 5, 1,  5, NULL, 'Net Worth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES ( 6, 1,  6, NULL, 'Source of these Funds');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES ( 7, 1,  7, NULL, 'Anticipated Account Turnover');
-- FSA Questions (Appropriateness Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES ( 8, 1,  8, NULL, 'Have you traded in any of these markets for the past 3 years?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES ( 9, 1,  9, NULL, 'How long have you been trading?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (10, 1, 10, NULL, 'Number of total trades a month?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (11, 1, 11, NULL, 'What was the average annual volume of your past transaction in standard lots?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (12, 1, 12, NULL, 'Do you understand the nature of the risk involved in trading margined products?');
-- FXTM Invest Questions (Suitability Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (13, 1, 13, NULL, 'What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (14, 1, 14, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (15, 1, 15, NULL, 'How would you describe your investments outside this program?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (16, 1, 16, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (17, 1, 17, NULL, 'What is your investment horizon?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (18, 1, 18, NULL, 'How often do you plan on withdrawing funds from your investment?');


-- 1. Date of Birth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (1, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (1, 1, '18 - 50',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (1, 2, '50 - 70', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (1, 3, '70 +',    -2);
-- 2. Occupation
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 1, 'Political/Public Office', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 2, 'Retired', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 3, 'Private company, Executive Management Board', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 4, 'Private Company, Other', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 5, 'Self-employed', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 6, 'Student', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 7, 'Public Sector/State, Executive Management/Board', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 8, 'Public Sector/State, Other', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (2, 9, 'Unemployed', -3);
-- 3. Level of Education
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (3, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (3, 1, 'None', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (3, 2, 'High School', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (3, 3, 'Graduate (Bachelors/Masters/PhD)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (3, 4, 'Professional Qualifications', 0);
-- 4. Annual Income
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (4, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (4, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (4, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (4, 3, 'More than 250,000',  0);
-- 5. Net Worth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (5, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (5, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (5, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (5, 3, 'More than 250,000',  0);
-- 6. Source of these Funds
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (6, 0, 'Unknown',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (6, 1, 'Employment',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (6, 2, 'Inheritance', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (6, 3, 'Investments', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (6, 4, 'Real Estate', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (6, 5, 'Savings',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (6, 6, 'Other',      -2);
-- 7. Anticipated Account Turnover
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (7, 0, 'Unknown',           0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (7, 1, 'Less than 10,000',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (7, 2, '10,000 - 50,000',  -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (7, 3, 'More than 50,000', -2);

-- 8. Have you traded in any of these markets for the past 3 years?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (8, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (8, 1, 'Yes (Forex / CFDs/Spread Betting / Bonds)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (8, 2, 'No Investment Experience', -7);
-- 9. How long have you been trading?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (9, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (9, 1, 'Less than 6 Months', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (9, 2, '6 - 12 Months', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (9, 3, 'More than 1 Year', 0);
-- 10. Number of total trades a month?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (10, 0, 'Unknown',              0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (10, 1, 'From 1 to 5 Months',  -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (10, 2, 'From 6 to 10 Months', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (10, 3, 'More than 10 Months',  0);
-- 11. What was the average annual volume of your past transaction in standard lots?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (11, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (11, 1, 'Less than 10 Lots', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (11, 2, '10 - 100 Lots',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (11, 3, 'More than 100 Lots', 0);
-- 12. Do you understand the nature of the risk involved in trading margined products?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (12, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (12, 1, 'Yes',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (12, 2, 'No',     -3);

-- 13. What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (13, 0, 'Unknown',       0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (13, 1, '0% - 20%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (13, 2, '20% - 50%',    -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (13, 3, 'More than 50%', 0);
-- 14. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (14, 0, 'Unknown',        0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (14, 1, 'Up to 30%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (14, 2, '30% - 50%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (14, 3, '50% - 70%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (14, 4, 'More than 70%', -3);
-- 15. How would you describe your investments outside this program?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (15, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (15, 1, 'Conservative', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (15, 2, 'Somewhat conservative', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (15, 3, 'Somewhat aggressive', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (15, 4, 'Aggressive', 0);
-- 16. The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (16, 0, 'Unknown',                                     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (16, 1, 'Risk of losing 10% with chance to gain 15%', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (16, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (16, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (16, 4, 'Risk of losing 80% with chance to gain 120%', 0);
-- 17. What is your investment horizon?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (17, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (17, 1, 'Up to 6 months', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (17, 2, '6 months to 1 year', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (17, 3, 'More than a year', 0);
-- 18. How often do you plan on withdrawing funds from your investment?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (18, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (18, 1, 'Regularly', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (18, 2, 'Occasionally', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (18, 3, 'When the scope of the investment ends', 0);



-- Version 2
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (2, 0, NOW(), NULL);

-- MyFXTM Questions
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (19, 2,  1, NULL, 'Date of Birth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (20, 2,  2, NULL, 'Occupation');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (21, 2,  3, NULL, 'Level of Education');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (22, 2,  4, NULL, 'Annual Income');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (23, 2,  5, NULL, 'Net Worth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (24, 2,  6, NULL, 'Source of Funds');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (25, 2,  7, NULL, 'Anticipated amount available to trade with us within the next 12 months');
-- FSA Questions (Appropriateness Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (26, 2,  8, NULL, 'How long have you been trading margined products?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (27, 2,  9, NULL, 'Which of the following instruments have you traded?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (28, 2, 10, NULL, 'What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?');
-- FXTM Invest Questions (Suitability Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (29, 2, 11, NULL, 'What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (30, 2, 12, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (31, 2, 13, NULL, 'How would you describe your investments outside this program?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (32, 2, 14, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (33, 2, 15, NULL, 'What is your investment horizon?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (34, 2, 16, NULL, 'How often do you plan on withdrawing funds from your investment?');


-- 1. Date of Birth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (19, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (19, 1, '18 - 50',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (19, 2, '50 - 70', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (19, 3, '70 +',    -2);
-- 2. Occupation
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 1, 'Retired', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 2, 'Private Company, Executive Management/Board', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 3, 'Private Company, Other', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 4, 'Self-employed', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 5, 'Student', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 6, 'Public Sector/State, Executive Management/Board', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 7, 'Public Sector/State, Other', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 8, 'Unemployed', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (20, 9, 'Private Company, Financial Services', 0);
-- 3. Level of Education
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (21, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (21, 1, 'None', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (21, 2, 'High School', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (21, 3, 'Graduate (BCs/MSs/PhD)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (21, 4, 'Professional Qualifications', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (21, 5, 'Graduate (BCs/MSs/PhD) in Financial Related Degree', 0);
-- 4. Annual Income
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (22, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (22, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (22, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (22, 3, 'More than 250,000',  0);
-- 5. Net Worth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (23, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (23, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (23, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (23, 3, 'More than 250,000',  0);
-- 6. Source of these Funds
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (24, 0, 'Unknown',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (24, 1, 'Employment',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (24, 2, 'Inheritance', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (24, 3, 'Investments', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (24, 4, 'Real Estate', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (24, 5, 'Savings',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (24, 6, 'Other',      -2);
-- 7. Anticipated amount available to trade with us within the next 12 months
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (25, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (25, 1, 'Less than 10,000',   0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (25, 2, '10,000 - 50,000',   -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (25, 3, '50,000 - 100,000',  -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (25, 4, 'More than 100,000', -2);

-- 8. How long have you been trading margined products?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (26, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (26, 1, '3+ years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (26, 2, '2-3 years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (26, 3, '1-2 years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (26, 4, 'Less than 12 months', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (26, 5, 'Never', -2);
-- 9. Which of the following instruments have you traded?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (27, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (27, 1, 'Yes (Forex | CFDs/Spread Betting | Other Margined Products | Shares/Bonds)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (27, 2, 'No trading experience', -7);
-- 10. What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (28, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (28, 1, 'More than 100 lots', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (28, 2, '10 - 100 lots',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (28, 3, 'Less than 10 lots', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (28, 4, '0',                 -3);

-- 11. What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (29, 0, 'Unknown',       0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (29, 1, '0% - 20%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (29, 2, '20% - 50%',    -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (29, 3, 'More than 50%', 0);
-- 12. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (30, 0, 'Unknown',        0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (30, 1, 'Up to 30%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (30, 2, '30% - 50%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (30, 3, '50% - 70%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (30, 4, 'More than 70%', -3);
-- 13. How would you describe your investments outside this program?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (31, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (31, 1, 'Conservative', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (31, 2, 'Somewhat conservative', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (31, 3, 'Somewhat aggressive', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (31, 4, 'Aggressive', 0);
-- 14 The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (32, 0, 'Unknown',                                     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (32, 1, 'Risk of losing 10% with chance to gain 15%', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (32, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (32, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (32, 4, 'Risk of losing 80% with chance to gain 120%', 0);
-- 15. What is your investment horizon?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (33, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (33, 1, 'Up to 6 months', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (33, 2, '6 months to 1 year', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (33, 3, 'More than a year', 0);
-- 16. How often do you plan on withdrawing funds from your investment?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (34, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (34, 1, 'Regularly', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (34, 2, 'Occasionally', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (34, 3, 'When the scope of the investment ends', 0);



-- Version 3
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (3, 0, NOW(), NULL);

-- MyFXTM Questions
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (35, 3,  1, NULL, 'Date of Birth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (36, 3,  2, NULL, 'Occupation');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (37, 3,  3, NULL, 'Level of Education');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (38, 3,  4, NULL, 'Annual Income');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (39, 3,  5, NULL, 'Net Worth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (40, 3,  6, NULL, 'Source of Funds');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (41, 3,  7, NULL, 'Anticipated amount available to trade with us within the next 12 months');
-- FSA Questions (Appropriateness Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (42, 3,  8, NULL, 'How long have you been trading margined products?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (43, 3,  9, NULL, 'Which of the following instruments have you traded?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (44, 3, 10, NULL, 'What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?');
-- FXTM Invest Questions (Suitability Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (45, 3, 11, NULL, 'What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (46, 3, 12, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (47, 3, 13, NULL, 'How would you describe your investments outside this program?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (48, 3, 14, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (49, 3, 15, NULL, 'What is your investment horizon?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (50, 3, 16, NULL, 'How often do you plan on withdrawing funds from your investment?');


-- 1. Date of Birth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (35, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (35, 1, '18 - 50',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (35, 2, '50 - 70', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (35, 3, '70 +',    -2);
-- 2. Occupation
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 1, 'Retired', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 2, 'Private Company, Executive Management/Board', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 3, 'Private Company, Other', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 4, 'Self-employed', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 5, 'Student', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 6, 'Public Sector/State, Executive Management/Board', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 7, 'Public Sector/State, Other', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 8, 'Unemployed', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (36, 9, 'Private Company, Financial Services', 0);
-- 3. Level of Education
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (37, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (37, 1, 'None', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (37, 2, 'High School', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (37, 3, 'Graduate (BCs/MSs/PhD)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (37, 4, 'Professional Qualifications', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (37, 5, 'Graduate (BCs/MSs/PhD) in Financial Related Degree', 0);
-- 4. Annual Income
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (38, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (38, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (38, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (38, 3, 'More than 250,000',  0);
-- 5. Net Worth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (39, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (39, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (39, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (39, 3, 'More than 250,000',  0);
-- 6. Source of these Funds
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (40, 0, 'Unknown',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (40, 1, 'Employment',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (40, 2, 'Inheritance', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (40, 3, 'Investments', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (40, 4, 'Real Estate', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (40, 5, 'Savings',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (40, 6, 'Other',      -2);
-- 7. Anticipated amount available to trade with us within the next 12 months
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (41, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (41, 1, 'Less than 10,000',   0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (41, 2, '10,000 - 50,000',   -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (41, 3, '50,000 - 100,000',  -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (41, 4, 'More than 100,000', -2);

-- 8. How long have you been trading margined products?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (42, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (42, 1, '3+ years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (42, 2, '1-3 years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (42, 3, 'Less than 12 months', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (42, 4, 'Never', -2);
-- 9. Which of the following instruments have you traded?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (43, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (43, 1, 'Yes (Forex | CFDs/Spread Betting | Other Margined Products | Shares/Bonds)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (43, 2, 'No trading experience', -7);
-- 10. What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (44, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (44, 1, 'More than 100 lots', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (44, 2, '10 - 100 lots',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (44, 3, 'Less than 10 lots', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (44, 4, '0',                 -3);

-- 11. What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (45, 0, 'Unknown',       0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (45, 1, '0% - 20%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (45, 2, '20% - 50%',    -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (45, 3, 'More than 50%', 0);
-- 12. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (46, 0, 'Unknown',        0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (46, 1, 'Up to 30%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (46, 2, '30% - 50%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (46, 3, '50% - 70%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (46, 4, 'More than 70%', -3);
-- 13. How would you describe your investments outside this program?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (47, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (47, 1, 'Conservative', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (47, 2, 'Somewhat conservative', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (47, 3, 'Somewhat aggressive', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (47, 4, 'Aggressive', 0);
-- 14 The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (48, 0, 'Unknown',                                     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (48, 1, 'Risk of losing 10% with chance to gain 15%', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (48, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (48, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (48, 4, 'Risk of losing 80% with chance to gain 120%', 0);
-- 15. What is your investment horizon?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (49, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (49, 1, 'Up to 6 months', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (49, 2, '6 months to 1 year', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (49, 3, 'More than a year', 0);
-- 16. How often do you plan on withdrawing funds from your investment?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (50, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (50, 1, 'Regularly', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (50, 2, 'Occasionally', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (50, 3, 'When the scope of the investment ends', 0);



-- Version 4
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (4, 0, NOW(), NULL);

-- MyFXTM Questions
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (51, 4,  1, NULL, 'Date of Birth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (52, 4,  2, NULL, 'Occupation');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (53, 4,  3, NULL, 'Level of Education');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (54, 4,  4, NULL, 'Annual Income');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (55, 4,  5, NULL, 'Net Worth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (56, 4,  6, NULL, 'Source of Funds');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (57, 4,  7, NULL, 'Anticipated amount available to trade with us within the next 12 months');
-- FSA Questions (Appropriateness Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (58, 4,  8, NULL, 'How long have you been trading margined products?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (59, 4,  9, NULL, 'Which of the following instruments have you traded?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (60, 4, 10, NULL, 'What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?');
-- FXTM Invest Questions (Suitability Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (61, 4, 11, NULL, 'What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (62, 4, 12, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (63, 4, 13, NULL, 'How would you describe your investments outside this program?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (64, 4, 14, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (65, 4, 15, NULL, 'What is your investment horizon?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (66, 4, 16, NULL, 'How often do you plan on withdrawing funds from your investment?');


-- 1. Date of Birth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (51, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (51, 1, '18 - 50',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (51, 2, '50 - 70', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (51, 3, '70 +',    -2);
-- 2. Occupation
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 1, 'Retired', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 2, 'Private Company, Executive Management/Board', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 3, 'Private Company, Other', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 4, 'Self-employed', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 5, 'Student', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 6, 'Public Sector/State, Executive Management/Board', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 7, 'Public Sector/State, Other', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 8, 'Unemployed', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (52, 9, 'Private Company, Financial Services', 0);
-- 3. Level of Education
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (53, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (53, 1, 'None', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (53, 2, 'High School', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (53, 3, 'Graduate (BCs/MSs/PhD)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (53, 4, 'Professional Qualifications', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (53, 5, 'Graduate (BCs/MSs/PhD) in Financial Related Degree', 0);
-- 4. Annual Income
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (54, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (54, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (54, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (54, 3, 'More than 250,000',  0);
-- 5. Net Worth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (55, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (55, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (55, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (55, 3, 'More than 250,000',  0);
-- 6. Source of these Funds
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (56, 0, 'Unknown',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (56, 1, 'Employment',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (56, 2, 'Inheritance', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (56, 3, 'Investments', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (56, 4, 'Real Estate', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (56, 5, 'Savings',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (56, 6, 'Other',      -2);
-- 7. Anticipated amount available to trade with us within the next 12 months
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (57, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (57, 1, 'Up to 100,000',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (57, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (57, 3, 'More than 250,000', -2);

-- 8. How long have you been trading margined products?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (58, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (58, 1, '3+ years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (58, 2, '1-3 years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (58, 3, 'Less than 12 months', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (58, 4, 'Never', -2);
-- 9. Which of the following instruments have you traded?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (59, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (59, 1, 'Yes (Forex | CFDs/Spread Betting | Other Margined Products | Shares/Bonds)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (59, 2, 'No trading experience', -7);
-- 10. What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (60, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (60, 1, 'More than 100 lots', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (60, 2, '10 - 100 lots',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (60, 3, 'Less than 10 lots', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (60, 4, '0',                 -3);

-- 11. What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (61, 0, 'Unknown',       0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (61, 1, '0% - 20%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (61, 2, '20% - 50%',    -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (61, 3, 'More than 50%', 0);
-- 12. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (62, 0, 'Unknown',        0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (62, 1, 'Up to 30%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (62, 2, '30% - 50%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (62, 3, '50% - 70%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (62, 4, 'More than 70%', -3);
-- 13. How would you describe your investments outside this program?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (63, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (63, 1, 'Conservative', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (63, 2, 'Somewhat conservative', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (63, 3, 'Somewhat aggressive', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (63, 4, 'Aggressive', 0);
-- 14 The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (64, 0, 'Unknown',                                     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (64, 1, 'Risk of losing 10% with chance to gain 15%', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (64, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (64, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (64, 4, 'Risk of losing 80% with chance to gain 120%', 0);
-- 15. What is your investment horizon?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (65, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (65, 1, 'Up to 6 months', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (65, 2, '6 months to 1 year', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (65, 3, 'More than a year', 0);
-- 16. How often do you plan on withdrawing funds from your investment?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (66, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (66, 1, 'Regularly', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (66, 2, 'Occasionally', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (66, 3, 'When the scope of the investment ends', 0);



-- Version 5
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (5, 0, NOW(), NULL);

-- MyFXTM Questions
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (67, 5,  1, NULL, 'Date of Birth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (68, 5,  2, NULL, 'Profession');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (69, 5,  3, NULL, 'Level of Education');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (70, 5,  4, NULL, 'Annual Income');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (71, 5,  5, NULL, 'Net Worth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (72, 5,  6, NULL, 'Source of Funds');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (73, 5,  7, NULL, 'Anticipated amount available to trade with us within the next 12 months');
-- FSA Questions (Appropriateness Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (74, 5,  8, NULL, 'How long have you been trading margined products?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (75, 5,  9, NULL, 'Which of the following instruments have you traded?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (76, 5, 10, NULL, 'What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?');
-- FXTM Invest Questions (Suitability Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (77, 5, 11, NULL, 'What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (78, 5, 12, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (79, 5, 13, NULL, 'How would you describe your investments outside this program?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (80, 5, 14, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (81, 5, 15, NULL, 'What is your investment horizon?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (82, 5, 16, NULL, 'How often do you plan on withdrawing funds from your investment?');


-- 1. Date of Birth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (67, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (67, 1, '18 - 50',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (67, 2, '50 - 70', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (67, 3, '70 +',    -2);
-- 2. Profession
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  1, 'Financial Services', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  2, 'Agricultural', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  3, 'Automotive', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  4, 'Construction & Property', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  5, 'Education', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  6, 'Health & Medicine', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  7, 'Hospitality & Catering', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  8, 'Legal', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68,  9, 'Media, Marketing & PR', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 10, 'Manufacturing', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 11, 'Telecommunications', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 12, 'Transport & Logistics', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 13, 'Public sector', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 14, 'Armed forces', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 15, 'Retired', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 16, 'Self-employed', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 17, 'Student', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 18, 'Unemployed', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (68, 19, 'Other profession', 0);
-- 3. Level of Education
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (69, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (69, 1, 'None', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (69, 2, 'High School', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (69, 3, 'Graduate (BCs/MSs/PhD)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (69, 4, 'Professional Qualifications', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (69, 5, 'Graduate (BCs/MSs/PhD) in Financial Related Degree', 0);
-- 4. Annual Income
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (70, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (70, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (70, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (70, 3, 'More than 250,000',  0);
-- 5. Net Worth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (71, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (71, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (71, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (71, 3, 'More than 250,000',  0);
-- 6. Source of these Funds
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (72, 0, 'Unknown',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (72, 1, 'Employment',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (72, 2, 'Inheritance', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (72, 3, 'Investments', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (72, 4, 'Real Estate', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (72, 5, 'Savings',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (72, 6, 'Other',      -2);
-- 7. Anticipated amount available to trade with us within the next 12 months
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (73, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (73, 1, 'Up to 100,000',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (73, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (73, 3, 'More than 250,000', -2);

-- 8. How long have you been trading margined products?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (74, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (74, 1, '3+ years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (74, 2, '1-3 years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (74, 3, 'Less than 12 months', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (74, 4, 'Never', -2);
-- 9. Which of the following instruments have you traded?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (75, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (75, 1, 'Yes (Forex | CFDs/Spread Betting | Other Margined Products | Shares/Bonds)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (75, 2, 'No trading experience', -7);
-- 10. What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (76, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (76, 1, 'More than 100 lots', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (76, 2, '10 - 100 lots',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (76, 3, 'Less than 10 lots', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (76, 4, '0',                 -3);

-- 11. What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (77, 0, 'Unknown',       0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (77, 1, '0% - 20%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (77, 2, '20% - 50%',    -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (77, 3, 'More than 50%', 0);
-- 12. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (78, 0, 'Unknown',        0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (78, 1, 'Up to 30%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (78, 2, '30% - 50%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (78, 3, '50% - 70%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (78, 4, 'More than 70%', -3);
-- 13. How would you describe your investments outside this program?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (79, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (79, 1, 'Conservative', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (79, 2, 'Somewhat conservative', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (79, 3, 'Somewhat aggressive', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (79, 4, 'Aggressive', 0);
-- 14 The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (80, 0, 'Unknown',                                     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (80, 1, 'Risk of losing 10% with chance to gain 15%', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (80, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (80, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (80, 4, 'Risk of losing 80% with chance to gain 120%', 0);
-- 15. What is your investment horizon?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (81, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (81, 1, 'Up to 6 months', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (81, 2, '6 months to 1 year', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (81, 3, 'More than a year', 0);
-- 16. How often do you plan on withdrawing funds from your investment?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (82, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (82, 1, 'Regularly', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (82, 2, 'Occasionally', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (82, 3, 'When the scope of the investment ends', 0);



-- Version 6
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (6, 0, NOW(), NULL);

-- MyFXTM Questions
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (83, 6,  1, NULL, 'Date of Birth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (84, 6,  2, NULL, 'Profession');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (85, 6,  3, NULL, 'Level of Education');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (86, 6,  4, NULL, 'Annual Income');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (87, 6,  5, NULL, 'Net Worth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (88, 6,  6, NULL, 'Source of Funds');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (89, 6,  7, NULL, 'Anticipated amount available to trade with us within the next 12 months');
-- FSA Questions (Appropriateness Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (90, 6,  8, NULL, 'Have you used or been engaged in any of the following services in the past?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (91, 6,  9, NULL, 'How long have you been trading in Forex/CFDs/Spread Betting margined products?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (92, 6, 10, NULL, 'How long have you been trading in Shares/Bonds margined products?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (93, 6, 11, NULL, 'What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?');
-- FXTM Invest Questions (Suitability Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (94, 6, 12, NULL, 'What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (95, 6, 13, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (96, 6, 14, NULL, 'How would you describe your investments outside this program?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (97, 6, 15, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (98, 6, 16, NULL, 'What is your investment horizon?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (99, 6, 17, NULL, 'How often do you plan on withdrawing funds from your investment?');


-- 1. Date of Birth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (83, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (83, 1, '18 - 50',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (83, 2, '50 - 70', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (83, 3, '70 +',    -2);
-- 2. Profession
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  1, 'Financial Services / employed by an investment firm that deals with Forex/CFDs/Spread Betting', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  2, 'Financial Services / in a financial consultancy firm', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  3, 'Agricultural', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  4, 'Automotive', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  5, 'Construction & Property', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  6, 'Education', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  7, 'Health & Medicine', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  8, 'Hospitality & Catering', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84,  9, 'Legal', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 10, 'Media, Marketing & PR', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 11, 'Manufacturing', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 12, 'Telecommunications', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 13, 'Transport & Logistics', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 14, 'Public sector', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 15, 'Armed forces', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 16, 'Retired', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 17, 'Self-employed', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 18, 'Student', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 19, 'Unemployed', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (84, 20, 'Other profession', 0);
-- 3. Level of Education
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (85, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (85, 1, 'None', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (85, 2, 'High School', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (85, 3, 'Graduate (BCs/MSs/PhD)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (85, 4, 'Professional Qualifications', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (85, 5, 'Graduate (BCs/MSs/PhD) in Financial Related Degree', 0);
-- 4. Annual Income
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (86, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (86, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (86, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (86, 3, 'More than 250,000',  0);
-- 5. Net Worth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (87, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (87, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (87, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (87, 3, 'More than 250,000',  0);
-- 6. Source of these Funds
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (88, 0, 'Unknown',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (88, 1, 'Employment',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (88, 2, 'Inheritance', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (88, 3, 'Investments', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (88, 4, 'Real Estate', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (88, 5, 'Savings',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (88, 6, 'Other',      -2);
-- 7. Anticipated amount available to trade with us within the next 12 months
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (89, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (89, 1, 'Up to 100,000',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (89, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (89, 3, 'More than 250,000', -2);

-- 8. Have you used or been engaged in any of the following services in the past?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (90, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (90, 1, 'Trading / Portfolio Management / Investment Advice', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (90, 2, 'I have no experience with financial services', -9);
-- 9. How long have you been trading in Forex/CFDs/Spread Betting margined products?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (91, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (91, 1, '3+ years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (91, 2, '1-3 years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (91, 3, 'Less than 12 months', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (91, 4, 'I have never traded such products', -4);
-- 10. How long have you been trading in Shares/Bonds margined products?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (92, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (92, 1, '3+ years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (92, 2, 'Less than 3 years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (92, 3, 'I have never traded such products', -4);
-- 11. What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (93, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (93, 1, 'More than 100 lots', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (93, 2, '10 - 100 lots',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (93, 3, 'Less than 10 lots', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (93, 4, '0',                 -3);

-- 12. What percentage of your net worth is liquid assets (%)? Liquid assets: cash, deposits. Illiquid assets: real estate and investments that cannot be easily sold
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (94, 0, 'Unknown',       0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (94, 1, '0% - 20%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (94, 2, '20% - 50%',    -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (94, 3, 'More than 50%', 0);
-- 13. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (95, 0, 'Unknown',        0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (95, 1, 'Up to 30%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (95, 2, '30% - 50%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (95, 3, '50% - 70%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (95, 4, 'More than 70%', -3);
-- 14. How would you describe your investments outside this program?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (96, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (96, 1, 'Conservative', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (96, 2, 'Somewhat conservative', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (96, 3, 'Somewhat aggressive', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (96, 4, 'Aggressive', 0);
-- 15 The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (97, 0, 'Unknown',                                     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (97, 1, 'Risk of losing 10% with chance to gain 15%', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (97, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (97, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (97, 4, 'Risk of losing 80% with chance to gain 120%', 0);
-- 16. What is your investment horizon?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (98, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (98, 1, 'Up to 6 months', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (98, 2, '6 months to 1 year', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (98, 3, 'More than a year', 0);
-- 17. How often do you plan on withdrawing funds from your investment?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (99, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (99, 1, 'Regularly', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (99, 2, 'Occasionally', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (99, 3, 'When the scope of the investment ends', 0);



-- Version 7
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (7, 0, NOW(), NULL);


-- MyFXTM Questions
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (100, 7,  1, NULL, 'Date of Birth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (101, 7,  2, NULL, 'Profession');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (102, 7,  3, NULL, 'Level of Education');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (103, 7,  4, NULL, 'Annual Income');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (104, 7,  5, NULL, 'Net Worth');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (105, 7,  6, NULL, 'Source of Funds');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (106, 7,  7, NULL, 'Anticipated amount available to trade with us within the next 12 months');

-- FSA Questions (Appropriateness Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (107, 7,  8, NULL, 'Have you used or been engaged in any of the following services in the past?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (108, 7,  9, NULL, 'How long have you been trading in Forex/CFDs/Spread Betting margined products?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (109, 7, 10, NULL, 'How long have you been trading in Shares/Bonds margined products?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (110, 7, 11, NULL, 'What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?');

-- FXTM Invest Questions (Suitability Test)
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (111, 7, 12, NULL, '1. Current wealth breakdown. Liquid assets (deposits, cash)');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (112, 7, 13, NULL, '2. Current wealth breakdown. Other Investments (Equities & Other Financial Assets Non CFDs/Forex)');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (113, 7, 14, NULL, '3. Current wealth breakdown. CFDs & Forex');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (114, 7, 15, NULL, '4. Current wealth breakdown. Real Estate');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (115, 7, 16, NULL, 'How many people on your family, besides yourself, do you entirely or partially support financially?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (116, 7, 17, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (117, 7, 18, NULL, 'How would you describe your investments outside this program?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (118, 7, 19, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (119, 7, 20, NULL, 'What is your investment horizon?');
INSERT INTO questionnaire_questions (id, questionnaire_id, `no`, parent_no, text) VALUES (120, 7, 21, NULL, 'How often do you plan on withdrawing funds from your investment?');


-- 1. Date of Birth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (100, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (100, 1, '18 - 50',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (100, 2, '50 - 70', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (100, 3, '70 +',    -2);

-- 2. Profession
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  1, 'Financial Services / employed by an investment firm that deals with Forex/CFDs/Spread Betting', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  2, 'Financial Services / in a financial consultancy firm', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  3, 'Agricultural', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  4, 'Automotive', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  5, 'Construction & Property', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  6, 'Education', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  7, 'Health & Medicine', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  8, 'Hospitality & Catering', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101,  9, 'Legal', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 10, 'Media, Marketing & PR', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 11, 'Manufacturing', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 12, 'Telecommunications', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 13, 'Transport & Logistics', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 14, 'Public sector', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 15, 'Armed forces', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 16, 'Retired', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 17, 'Self-employed', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 18, 'Student', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 19, 'Unemployed', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (101, 20, 'Other profession', 0);

-- 3. Level of Education
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (102, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (102, 1, 'None', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (102, 2, 'High School', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (102, 3, 'Graduate (BCs/MSs/PhD)', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (102, 4, 'Professional Qualifications', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (102, 5, 'Graduate (BCs/MSs/PhD) in Financial Related Degree', 0);

-- 4. Annual Income
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (103, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (103, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (103, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (103, 3, 'More than 250,000',  0);

-- 5. Net Worth
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (104, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (104, 1, 'Less than 100,000', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (104, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (104, 3, 'More than 250,000',  0);

-- 6. Source of these Funds
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (105, 0, 'Unknown',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (105, 1, 'Employment',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (105, 2, 'Inheritance', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (105, 3, 'Investments', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (105, 4, 'Real Estate', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (105, 5, 'Savings',     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (105, 6, 'Other',      -2);

-- 7. Anticipated amount available to trade with us within the next 12 months
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (106, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (106, 1, 'Up to 100,000',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (106, 2, '100,000 - 250,000', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (106, 3, 'More than 250,000', -2);


-- 8. Have you used or been engaged in any of the following services in the past?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (107, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (107, 1, 'Trading / Portfolio Management / Investment Advice', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (107, 2, 'I have no experience with financial services', -9);

-- 9. How long have you been trading in Forex/CFDs/Spread Betting margined products?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (108, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (108, 1, '3+ years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (108, 2, '1-3 years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (108, 3, 'Less than 12 months', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (108, 4, 'I have never traded such products', -4);

-- 10. How long have you been trading in Shares/Bonds margined products?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (109, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (109, 1, '3+ years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (109, 2, 'Less than 3 years', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (109, 3, 'I have never traded such products', -4);

-- 11. What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (110, 0, 'Unknown',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (110, 1, 'More than 100 lots', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (110, 2, '10 - 100 lots',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (110, 3, 'Less than 10 lots', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (110, 4, '0',                 -3);


-- 12. - 1. Liquid assets (deposits, cash)
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (111, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (111, 1, '0%',      -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (111, 2, '25%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (111, 3, '50%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (111, 4, '75%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (111, 5, '100%',     0);
-- 13. - 2. Other Investments (Equities & Other Financial Assets Non CFDs/Forex)
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (112, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (112, 1, '0%',       0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (112, 2, '25%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (112, 3, '50%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (112, 4, '75%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (112, 5, '100%',    -3);
-- 14. - 3. CFDs & Forex
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (113, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (113, 1, '0%',       0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (113, 2, '25%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (113, 3, '50%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (113, 4, '75%',     -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (113, 5, '100%',    -5);
-- 15. - 4. Real Estate
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (114, 0, 'Unknown',  0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (114, 1, '0%',       0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (114, 2, '25%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (114, 3, '50%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (114, 4, '75%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (114, 5, '100%',    -2);

-- 16. How many people on your family, besides yourself, do you entirely or partially support financially?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (115, 0, 'Unknown',        0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (115, 1, 'None',           0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (115, 2, 'One',            0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (115, 3, 'Two',           -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (115, 4, 'Three or more', -2);

-- 17. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (116, 0, 'Unknown',        0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (116, 1, 'Up to 30%',      0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (116, 2, '30% - 50%',     -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (116, 3, '50% - 70%',     -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (116, 4, 'More than 70%', -3);

-- 18. How would you describe your investments outside this program?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (117, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (117, 1, 'Conservative', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (117, 2, 'Somewhat conservative', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (117, 3, 'Somewhat aggressive', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (117, 4, 'Aggressive', 0);

-- 19. The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (118, 0, 'Unknown',                                     0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (118, 1, 'Risk of losing 10% with chance to gain 15%', -3);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (118, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (118, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (118, 4, 'Risk of losing 80% with chance to gain 120%', 0);

-- 20. What is your investment horizon?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (119, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (119, 1, 'Up to 6 months', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (119, 2, '6 months to 1 year', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (119, 3, 'More than a year', 0);

-- 21. How often do you plan on withdrawing funds from your investment?
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (120, 0, 'Unknown', 0);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (120, 1, 'Regularly', -2);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (120, 2, 'Occasionally', -1);
INSERT INTO questionnaire_questions_choices (question_id, `no`, text, points) VALUES (120, 3, 'When the scope of the investment ends', 0);


-- Version 8
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (8, 0, NOW(), NULL);


-- MyFXTM Questions
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (121, 8, 1, NULL, 'Date of Birth');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (122, 8, 2, NULL, 'Profession');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (123, 8, 3, NULL, 'Level of Education');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (124, 8, 4, NULL, 'Annual Income');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (125, 8, 5, NULL, 'Net Worth');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (126, 8, 6, NULL, 'Source of Funds');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (127, 8, 7, NULL, 'Anticipated amount available to trade with us within the next 12 months');


-- FSA Questions (Appropriateness Test)
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (128, 8, 8, NULL, 'Have you used or been engaged in any of the following services in the past?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (129, 8, 9, NULL, 'How long have you been trading in Forex/CFDs/Spread Betting margined products?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (130, 8, 10, NULL, 'How long have you been trading in Shares/Bonds margined products?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (131, 8, 11, NULL, 'What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?');


-- FXTM Invest Questions (Suitability Test)
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (132, 8, 12, NULL, '1. Current wealth breakdown. Liquid assets (deposits, cash)');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (133, 8, 13, NULL, '2. Current wealth breakdown. Other Investments (Equities & Other Financial Assets Non CFDs/Forex)');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (134, 8, 14, NULL, '3. Current wealth breakdown. CFDs & Forex');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (135, 8, 15, NULL, '4. Current wealth breakdown. Real Estate');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (136, 8, 16, NULL, 'How many people on your family, besides yourself, do you entirely or partially support financially?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (137, 8, 17, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (138, 8, 18, NULL, 'How would you describe your investments outside this program?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (139, 8, 19, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (140, 8, 20, NULL, 'What is your investment horizon?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (141, 8, 21, NULL, 'How often do you plan on withdrawing funds from your investment?');


-- 1. Date of Birth
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (121, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (121, 1, '18 - 50', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (121, 2, '50 - 70', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (121, 3, '70 +', -2);

-- 2. Profession
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 1, 'Financial Services / employed by an investment firm that deals with Forex/CFDs/Spread Betting', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 2, 'Financial Services / in a financial consultancy firm', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 3, 'Agricultural', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 4, 'Automotive', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 5, 'Construction & Property', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 6, 'Education', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 7, 'Health & Medicine', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 8, 'Hospitality & Catering', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 9, 'Legal', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 10, 'Media, Marketing & PR', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 11, 'Manufacturing', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 12, 'Telecommunications', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 13, 'Transport & Logistics', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 14, 'Public sector', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 15, 'Armed forces', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 16, 'Retired', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 17, 'Self-employed', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 18, 'Student', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 19, 'Unemployed', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (122, 20, 'Other profession', 0);

-- 3. Level of Education
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (123, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (123, 1, 'None', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (123, 2, 'High School', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (123, 3, 'Graduate (BCs/MSs/PhD)', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (123, 4, 'Professional Qualifications', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (123, 5, 'Graduate (BCs/MSs/PhD) in Financial Related Degree', 0);

-- 4. Annual Income
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (124, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (124, 1, 'Less than 25,000', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (124, 2, '25,000 - 100,000', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (124, 3, '100,000 - 250,000', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (124, 4, 'More than 250,000', 0);

-- 5. Net Worth
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (125, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (125, 1, 'Less than 100,000', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (125, 2, '100,000 - 250,000', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (125, 3, 'More than 250,000', 0);

-- 6. Source of these Funds
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (126, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (126, 1, 'Employment', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (126, 2, 'Inheritance', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (126, 3, 'Investments', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (126, 4, 'Real Estate', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (126, 5, 'Savings', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (126, 6, 'Other', -2);

-- 7. Anticipated amount available to trade with us within the next 12 months
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (127, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (127, 1, 'Up to 100,000', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (127, 2, '100,000 - 250,000', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (127, 3, 'More than 250,000', -2);

-- 8. Have you used or been engaged in any of the following services in the past?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (128, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (128, 1, 'Trading / Portfolio Management / Investment Advice', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (128, 2, 'I have no experience with financial services', -9);

-- 9. How long have you been trading in Forex/CFDs/Spread Betting margined products?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (129, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (129, 1, '3+ years', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (129, 2, '1-3 years', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (129, 3, 'Less than 12 months', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (129, 4, 'I have never traded such products', -4);

-- 10. How long have you been trading in Shares/Bonds margined products?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (130, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (130, 1, '3+ years', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (130, 2, 'Less than 3 years', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (130, 3, 'I have never traded such products', -4);

-- 11. What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (131, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (131, 1, 'More than 100 lots', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (131, 2, '10 - 100 lots', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (131, 3, 'Less than 10 lots', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (131, 4, '0', -3);

-- 12. - 1. Liquid assets (deposits, cash)
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (132, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (132, 1, '0%', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (132, 2, '25%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (132, 3, '50%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (132, 4, '75%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (132, 5, '100%', 0);

-- 13. - 2. Other Investments (Equities & Other Financial Assets Non CFDs/Forex)
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (133, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (133, 1, '0%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (133, 2, '25%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (133, 3, '50%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (133, 4, '75%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (133, 5, '100%', -3);

-- 14. - 3. CFDs & Forex
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (134, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (134, 1, '0%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (134, 2, '25%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (134, 3, '50%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (134, 4, '75%', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (134, 5, '100%', -5);

-- 15. - 4. Real Estate
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (135, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (135, 1, '0%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (135, 2, '25%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (135, 3, '50%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (135, 4, '75%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (135, 5, '100%', -2);

-- 16. How many people on your family, besides yourself, do you entirely or partially support financially?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (136, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (136, 1, 'None', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (136, 2, 'One', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (136, 3, 'Two', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (136, 4, 'Three or more', -2);

-- 17. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (137, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (137, 1, 'Up to 30%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (137, 2, '30% - 50%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (137, 3, '50% - 70%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (137, 4, 'More than 70%', -3);

-- 18. How would you describe your investments outside this program?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (138, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (138, 1, 'Conservative', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (138, 2, 'Somewhat conservative', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (138, 3, 'Somewhat aggressive', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (138, 4, 'Aggressive', 0);

-- 19. The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (139, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (139, 1, 'Risk of losing 10% with chance to gain 15%', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (139, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (139, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (139, 4, 'Risk of losing 80% with chance to gain 120%', 0);

-- 20. What is your investment horizon?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (140, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (140, 1, 'Up to 6 months', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (140, 2, '6 months to 1 year', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (140, 3, 'More than a year', 0);

-- 21. How often do you plan on withdrawing funds from your investment?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (141, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (141, 1, 'Regularly', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (141, 2, 'Occasionally', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (141, 3, 'When the scope of the investment ends', 0);



-- Version 9
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (9, 0, NOW(), NULL);

-- MyFXTM Questions
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (142, 9, 1, NULL, 'Date of Birth');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (143, 9, 2, NULL, 'Profession');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (144, 9, 3, NULL, 'Level of Education');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (145, 9, 4, NULL, 'Annual Income');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (146, 9, 5, NULL, 'Net Worth');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (147, 9, 6, NULL, 'Source of Funds');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (148, 9, 7, NULL, 'Anticipated amount available to trade with us within the next 12 months');

-- FSA Questions (Appropriateness Test)
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (149, 9, 8, NULL, 'Have you used or been engaged in any of the following services in the past?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (150, 9, 9, NULL, 'How long have you been trading in Forex/CFDs/Spread Betting margined products?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (151, 9, 10, NULL, 'Frequency');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (152, 9, 11, NULL, 'What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?');

-- FXTM Invest Questions (Suitability Test)
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (153, 9, 12, NULL, '1. Current wealth breakdown. Liquid assets (deposits, cash)');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (154, 9, 13, NULL, '2. Current wealth breakdown. Other Investments (Equities & Other Financial Assets Non CFDs/Forex)');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (155, 9, 14, NULL, '3. Current wealth breakdown. CFDs & Forex');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (156, 9, 15, NULL, '4. Current wealth breakdown. Real Estate');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (157, 9, 16, NULL, 'How many people on your family, besides yourself, do you entirely or partially support financially?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (158, 9, 17, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (159, 9, 18, NULL, 'How would you describe your investments outside this program?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (160, 9, 19, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (161, 9, 20, NULL, 'What is your investment horizon?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (162, 9, 21, NULL, 'How often do you plan on withdrawing funds from your investment?');

-- 1. Date of Birth
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (142, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (142, 1, '18 - 50', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (142, 2, '50 - 70', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (142, 3, '70 +', -4);

-- 2. Profession
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 1, 'Financial Services / employed by an investment firm that deals with Forex/CFDs/Spread Betting', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 2, 'Financial Services / in a financial consultancy firm', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 3, 'Agricultural', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 4, 'Automotive', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 5, 'Construction & Property', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 6, 'Education', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 7, 'Health & Medicine', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 8, 'Hospitality & Catering', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 9, 'Legal', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 10, 'Media, Marketing & PR', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 11, 'Manufacturing', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 12, 'Telecommunications', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 13, 'Transport & Logistics', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 14, 'Public sector', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 15, 'Armed forces', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 16, 'Retired', -4);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 17, 'Self-employed', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 18, 'Student', -4);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 19, 'Unemployed', -4);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (143, 20, 'Other profession', 0);

-- 3. Level of Education
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (144, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (144, 1, 'None', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (144, 2, 'High School', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (144, 3, 'Graduate (BCs/MSs/PhD)', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (144, 4, 'Professional Qualifications', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (144, 5, 'Graduate (BCs/MSs/PhD) in Financial Related Degree', 0);

-- 4. Annual Income
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (145, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (145, 1, 'Less than 25,000', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (145, 2, '25,000 - 100,000', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (145, 3, '100,000 - 250,000', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (145, 4, 'More than 250,000', 0);

-- 5. Net Worth
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (146, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (146, 1, 'Less than 100,000', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (146, 2, '100,000 - 250,000', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (146, 3, 'More than 250,000', 0);

-- 6. Source of these Funds
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (147, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (147, 1, 'Employment', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (147, 2, 'Inheritance', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (147, 3, 'Investments', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (147, 4, 'Real Estate', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (147, 5, 'Savings', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (147, 6, 'Other', -2);

-- 7. Anticipated amount available to trade with us within the next 12 months
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (148, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (148, 1, 'Up to 100,000', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (148, 2, '100,000 - 250,000', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (148, 3, 'More than 250,000', -2);

-- 8. Have you used or been engaged in any of the following services in the past?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (149, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (149, 1, 'Trading / Portfolio Management / Investment Advice', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (149, 2, 'I have no experience with financial services', -9);

-- 9. How long have you been trading in Forex/CFDs/Spread Betting margined products?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (150, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (150, 1, '3+ years', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (150, 2, '1-3 years', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (150, 3, 'Less than 12 months', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (150, 4, 'I have never traded such products', -4);

-- 10. Frequency
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (151, 0, 'Daily', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (151, 1, 'Weekly', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (151, 2, 'Monthly', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (151, 3, 'Yearly', -4);

-- 11. What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (152, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (152, 1, 'More than 100 lots', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (152, 2, '10 - 100 lots', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (152, 3, 'Less than 10 lots', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (152, 4, '0', -4);

-- 12. - 1. Liquid assets (deposits, cash)
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (153, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (153, 1, '0%', -5);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (153, 2, '25%', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (153, 3, '50%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (153, 4, '75%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (153, 5, '100%', 0);

-- 13. - 2. Other Investments (Equities & Other Financial Assets Non CFDs/Forex)
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (154, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (154, 1, '0%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (154, 2, '25%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (154, 3, '50%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (154, 4, '75%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (154, 5, '100%', -3);

-- 14. - 3. CFDs & Forex
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (155, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (155, 1, '0%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (155, 2, '25%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (155, 3, '50%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (155, 4, '75%', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (155, 5, '100%', -5);

-- 15. - 4. Real Estate
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (156, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (156, 1, '0%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (156, 2, '25%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (156, 3, '50%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (156, 4, '75%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (156, 5, '100%', -2);

-- 16. How many people on your family, besides yourself, do you entirely or partially support financially?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (157, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (157, 1, 'None', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (157, 2, 'One', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (157, 3, 'Two', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (157, 4, 'Three or more', -4);

-- 17. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (158, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (158, 1, 'Up to 30%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (158, 2, '30% - 50%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (158, 3, '50% - 70%', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (158, 4, 'More than 70%', -8);

-- 18. How would you describe your investments outside this program?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (159, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (159, 1, 'Conservative', -8);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (159, 2, 'Somewhat conservative', -4);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (159, 3, 'Somewhat aggressive', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (159, 4, 'Aggressive', 0);

-- 19. The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (160, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (160, 1, 'Risk of losing 10% with chance to gain 15%', -50);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (160, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (160, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (160, 4, 'Risk of losing 80% with chance to gain 120%', 0);

-- 20. What is your investment horizon?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (161, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (161, 1, 'Up to 6 months', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (161, 2, '6 months to 1 year', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (161, 3, 'More than a year', 0);

-- 21. How often do you plan on withdrawing funds from your investment?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (162, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (162, 1, 'Regularly', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (162, 2, 'Occasionally', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (162, 3, 'When the scope of the investment ends', 0);


-- Version 10
INSERT INTO questionnaire (id, status, created_at, published_at) VALUES (10, 1, NOW(), NULL);

-- MyFXTM Questions
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (163, 10, 1, NULL, 'Date of Birth');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (164, 10, 2, NULL, 'Profession');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (165, 10, 3, NULL, 'Level of Education');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (166, 10, 4, NULL, 'Annual Income');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (167, 10, 5, NULL, 'Net Worth');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (168, 10, 6, NULL, 'Source of Funds');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (169, 10, 7, NULL, 'Anticipated amount available to trade with us within the next 12 months');

-- FSA Questions (Appropriateness Test)
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (170, 10, 8, NULL, 'Have you used or been engaged in any of the following services in the past?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (171, 10, 9, NULL, 'How long have you been trading in Forex/CFDs/Spread Betting margined products?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (172, 10, 10, NULL, 'Frequency');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (173, 10, 11, NULL, 'What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?');

-- Questions Knowledge
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (174, 10, 12, NULL, 'Knowledge');

-- FXTM Invest Questions (Suitability Test)
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (175, 10, 13, NULL, '1. Current wealth breakdown. Liquid assets (deposits, cash)');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (176, 10, 14, NULL, '2. Current wealth breakdown. Other Investments (Equities & Other Financial Assets Non CFDs/Forex)');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (177, 10, 15, NULL, '3. Current wealth breakdown. CFDs & Forex');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (178, 10, 16, NULL, '4. Current wealth breakdown. Real Estate');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (179, 10, 17, NULL, 'How many people on your family, besides yourself, do you entirely or partially support financially?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (180, 10, 18, NULL, 'What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (181, 10, 19, NULL, 'How would you describe your investments outside this program?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (182, 10, 20, NULL, 'The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (183, 10, 21, NULL, 'What is your investment horizon?');
INSERT INTO `questionnaire_questions` (`id`, `questionnaire_id`, `no`, `parent_no`, `text`) VALUES (184, 10, 22, NULL, 'How often do you plan on withdrawing funds from your investment?');

-- 1. Date of Birth
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (163, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (163, 1, '18 - 50', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (163, 2, '50 - 70', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (163, 3, '70 +', -4);

-- 2. Profession
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 1, 'Financial Services / employed by an investment firm that deals with Forex/CFDs/Spread Betting', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 2, 'Financial Services / in a financial consultancy firm', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 3, 'Agricultural', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 4, 'Automotive', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 5, 'Construction & Property', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 6, 'Education', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 7, 'Health & Medicine', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 8, 'Hospitality & Catering', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 9, 'Legal', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 10, 'Media, Marketing & PR', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 11, 'Manufacturing', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 12, 'Telecommunications', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 13, 'Transport & Logistics', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 14, 'Public sector', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 15, 'Armed forces', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 16, 'Retired', -4);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 17, 'Self-employed', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 18, 'Student', -4);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 19, 'Unemployed', -4);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (164, 20, 'Other profession', 0);

-- 3. Level of Education
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (165, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (165, 1, 'None', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (165, 2, 'High School', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (165, 3, 'Graduate (BCs/MSs/PhD)', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (165, 4, 'Professional Qualifications', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (165, 5, 'Graduate (BCs/MSs/PhD) in Financial Related Degree', 0);

-- 4. Annual Income
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (166, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (166, 1, 'Less than 25,000', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (166, 2, '25,000 - 100,000', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (166, 3, '100,000 - 250,000', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (166, 4, 'More than 250,000', 0);

-- 5. Net Worth
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (167, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (167, 1, 'Less than 100,000', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (167, 2, '100,000 - 250,000', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (167, 3, 'More than 250,000', 0);

-- 6. Source of these Funds
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (168, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (168, 1, 'Employment', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (168, 2, 'Inheritance', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (168, 3, 'Investments', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (168, 4, 'Real Estate', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (168, 5, 'Savings', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (168, 6, 'Other', -2);

-- 7. Anticipated amount available to trade with us within the next 12 months
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (169, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (169, 1, 'Up to 100,000', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (169, 2, '100,000 - 250,000', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (169, 3, 'More than 250,000', -2);

-- 8. Have you used or been engaged in any of the following services in the past?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (170, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (170, 1, 'Trading / Portfolio Management / Investment Advice', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (170, 2, 'I have no experience with financial services', -9);

-- 9. How long have you been trading in Forex/CFDs/Spread Betting margined products?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (171, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (171, 1, '3+ years', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (171, 2, '1-3 years', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (171, 3, 'Less than 12 months', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (171, 4, 'I have never traded such products', -4);

-- 10. Frequency
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (172, 0, 'Daily', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (172, 1, 'Weekly', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (172, 2, 'Monthly', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (172, 3, 'Yearly', -4);

-- 11. What was the average quarterly volume of your past transactions in standard lots in leveraged products within the past year?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (173, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (173, 1, 'More than 100 lots', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (173, 2, '10 - 100 lots', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (173, 3, 'Less than 10 lots', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (173, 4, '0', -4);

-- 12. Knowledge
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (174, 0, '0 - 34', -15);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (174, 1, '35 - 40', -4);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (174, 2, '41 - 44', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (174, 3, '45 - 51', 0);

-- 13. - 1. Liquid assets (deposits, cash)
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (175, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (175, 1, '0%', -5);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (175, 2, '25%', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (175, 3, '50%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (175, 4, '75%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (175, 5, '100%', 0);

-- 14. - 2. Other Investments (Equities & Other Financial Assets Non CFDs/Forex)
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (176, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (176, 1, '0%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (176, 2, '25%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (176, 3, '50%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (176, 4, '75%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (176, 5, '100%', -3);

-- 15. - 3. CFDs & Forex
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (177, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (177, 1, '0%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (177, 2, '25%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (177, 3, '50%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (177, 4, '75%', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (177, 5, '100%', -5);

-- 16. - 4. Real Estate
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (178, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (178, 1, '0%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (178, 2, '25%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (178, 3, '50%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (178, 4, '75%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (178, 5, '100%', -2);

-- 17. How many people on your family, besides yourself, do you entirely or partially support financially?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (179, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (179, 1, 'None', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (179, 2, 'One', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (179, 3, 'Two', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (179, 4, 'Three or more', -4);

-- 18. What percentage of your total monthly income goes towards your mortgage(s) and other financial commitments?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (180, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (180, 1, 'Up to 30%', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (180, 2, '30% - 50%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (180, 3, '50% - 70%', -3);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (180, 4, 'More than 70%', -8);

-- 19. How would you describe your investments outside this program?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (181, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (181, 1, 'Conservative', -8);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (181, 2, 'Somewhat conservative', -4);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (181, 3, 'Somewhat aggressive', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (181, 4, 'Aggressive', 0);

-- 20. The scenarios below show the greatest possible gain/loss over a set period of time for 4 hypothetical investments. Given the potential gain or loss, which of the following scenarios describes your investment style and appetite for risk?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (182, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (182, 1, 'Risk of losing 10% with chance to gain 15%', -50);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (182, 2, 'Risk of losing 30% with chance to gain 55%', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (182, 3, 'Risk of losing 50% with chance to gain 75%', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (182, 4, 'Risk of losing 80% with chance to gain 120%', 0);

-- 21. What is your investment horizon?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (183, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (183, 1, 'Up to 6 months', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (183, 2, '6 months to 1 year', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (183, 3, 'More than a year', 0);

-- 22. How often do you plan on withdrawing funds from your investment?
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (184, 0, 'Unknown', 0);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (184, 1, 'Regularly', -2);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (184, 2, 'Occasionally', -1);
INSERT INTO `questionnaire_questions_choices` (`question_id`, `no`, `text`, `points`) VALUES (184, 3, 'When the scope of the investment ends', 0);