CREATE TABLE IF NOT EXISTS /*_*/wikispeech_utterance(
-- Primary key. Also used as the file name prefix of the stored utterance audio and metadata.
wsu_utterance_id int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,
-- page.page_id containing the text segment the utterance represents.
wsu_page_id int unsigned NOT NULL,
-- page.page_lang or site.site_language.
wsu_lang varbinary(35) NOT NULL,
-- Hash of text segment represented as synthesized utterance. SHA256 as lower case HEX string.
wsu_seg_hash char(64) NOT NULL,
-- Name of voice used in utterance.
wsu_voice varchar(30) NOT NULL,
-- Timestamp of when the utterance was stored in database. Used to flush out old utterances on update.
wsu_date_stored binary(14) NOT NULL
)/*$wgDBTableOptions*/;

-- Used when fetching a specific utterance in a page.
CREATE INDEX /*i*/get_utterance ON /*_*/wikispeech_utterance (wsu_page_id, wsu_lang, wsu_voice, wsu_seg_hash);
-- Used to flush all utterances for a given page.
CREATE INDEX /*i*/expire_page_utterances ON /*_*/wikispeech_utterance (wsu_page_id);
-- Used to flush all utterances for a specific language due to improvements of synthesis.
CREATE INDEX /*i*/expire_utterances_lang ON /*_*/wikispeech_utterance (wsu_lang);
-- Used to flush all utterances for a specific language in a specific voice due to improvements of synthesis.
CREATE INDEX /*i*/expire_utterances_lang_voice ON /*_*/wikispeech_utterance (wsu_lang, wsu_voice);
-- Used to automatically flush utterances that are older than a time-to-live setting.
CREATE INDEX /*i*/expire_utterances_ttl ON /*_*/wikispeech_utterance (wsu_date_stored);
