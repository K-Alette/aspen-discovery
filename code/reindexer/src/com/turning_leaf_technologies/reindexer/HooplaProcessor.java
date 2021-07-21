package com.turning_leaf_technologies.reindexer;

import com.turning_leaf_technologies.indexing.HooplaScope;
import com.turning_leaf_technologies.indexing.Scope;
import com.turning_leaf_technologies.logging.BaseLogEntry;
import com.turning_leaf_technologies.strings.StringUtils;
import org.apache.logging.log4j.Logger;
import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import org.json.JSONString;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Date;
import java.util.HashMap;
import java.util.HashSet;

class HooplaProcessor {
	private final GroupedWorkIndexer indexer;
	private final Logger logger;

	private PreparedStatement getProductInfoStmt;

	HooplaProcessor(GroupedWorkIndexer indexer, Connection dbConn, Logger logger) {
		this.indexer = indexer;
		this.logger = logger;

		try {
			getProductInfoStmt = dbConn.prepareStatement("SELECT id, hooplaId, active, title, kind, pa, demo, profanity, rating, abridged, children, price, rawChecksum, UNCOMPRESS(rawResponse) as rawResponse, dateFirstDetected from hoopla_export where hooplaId = ?", ResultSet.TYPE_FORWARD_ONLY,  ResultSet.CONCUR_READ_ONLY);
		} catch (SQLException e) {
			logger.error("Error setting up hoopla processor", e);
		}
	}

	void processRecord(GroupedWorkSolr groupedWork, String identifier, BaseLogEntry logEntry) {
		try {
			getProductInfoStmt.setString(1, identifier);
			ResultSet productRS = getProductInfoStmt.executeQuery();
			if (productRS.next()) {
				//Make sure the record isn't deleted
				if (!productRS.getBoolean("active")){
					logger.debug("Hoopla product " + identifier + " is inactive, skipping");
					return;
				}
				String kind = productRS.getString("kind");
				float price = productRS.getFloat("price");

				RecordInfo hooplaRecord = groupedWork.addRelatedRecord("hoopla", identifier);
				hooplaRecord.setRecordIdentifier("hoopla", identifier);

				String title = productRS.getString("title");
				String subTitle = "";

				String formatCategory;
				String primaryFormat;
				switch (kind) {
					case "MOVIE":
					case "TELEVISION":
						formatCategory = "Movies";
						primaryFormat = "eVideo";
						break;
					case "AUDIOBOOK":
						formatCategory = "Audio Books";
						hooplaRecord.addFormatCategory("eBook");
						primaryFormat = "eAudiobook";
						break;
					case "EBOOK":
						formatCategory = "eBook";
						primaryFormat = "eBook";
						break;
					case "COMIC":
						formatCategory = "eBook";
						primaryFormat = "eComic";
						break;
					case "MUSIC":
						formatCategory = "Music";
						primaryFormat = "eMusic";
						break;
					default:
						logger.error("Unhandled hoopla kind " + kind);
						formatCategory = kind;
						primaryFormat = kind;
						break;
				}

				hooplaRecord.addFormat(primaryFormat);
				hooplaRecord.addFormatCategory(formatCategory);

				JSONObject rawResponse = new JSONObject(productRS.getString("rawResponse"));

				if (rawResponse.has("titleTitle")){
					title = rawResponse.getString("titleTitle");
					subTitle = rawResponse.getString("title");
				}

				String fullTitle = title + " " + subTitle;
				fullTitle = fullTitle.trim();
				groupedWork.setTitle(title, subTitle, title, title, primaryFormat);
				groupedWork.addFullTitle(fullTitle);


				String primaryAuthor = "";
				if (rawResponse.has("artist")){
					primaryAuthor = rawResponse.getString("artist");
					primaryAuthor = StringUtils.swapFirstLastNames(primaryAuthor);
				}else if (rawResponse.has("publisher")){
					primaryAuthor = rawResponse.getString("publisher");
				}
				groupedWork.setAuthor(primaryAuthor);
				groupedWork.setAuthAuthor(primaryAuthor);
				groupedWork.setAuthorDisplay(primaryAuthor);

				if (rawResponse.has("series")){
					String series = rawResponse.getString("series");
					groupedWork.addSeries(series);
					String volume = "";
					if (rawResponse.has("episode")){
						volume = rawResponse.get("episode").toString();
					}
					groupedWork.addSeriesWithVolume(series, volume);
				}

				boolean children = rawResponse.getBoolean("children");
				if (children){
					groupedWork.addTargetAudience("Juvenile");
					groupedWork.addTargetAudienceFull("Juvenile");
				}else{
					if (rawResponse.has("rating")){
						String rating = rawResponse.getString("rating");
						if (rating.equals("TVMA") || rating.equals("M") || rating.equals("NC17")){
							groupedWork.addTargetAudience("Adult");
							groupedWork.addTargetAudienceFull("Adult");
						}else {
							groupedWork.addTargetAudience("Young Adult");
							groupedWork.addTargetAudienceFull("Adolescent (14-17)");
							groupedWork.addTargetAudience("Adult");
							groupedWork.addTargetAudienceFull("Adult");
						}
					}else{
						groupedWork.addTargetAudience("Adult");
						groupedWork.addTargetAudienceFull("Adult");
					}
				}

				String language = rawResponse.getString("language");
				language = org.apache.commons.lang3.StringUtils.capitalize(language.toLowerCase());
				hooplaRecord.setPrimaryLanguage(language);
				long formatBoost = 1;
				try {
					formatBoost = Long.parseLong(indexer.translateSystemValue("format_boost_hoopla", primaryFormat, identifier));
				} catch (Exception e) {
					logger.warn("Could not translate format boost for " + primaryFormat + " create translation map format_boost_hoopla");
				}
				hooplaRecord.setFormatBoost(formatBoost);
				if (rawResponse.has("artists")) {
					JSONArray artists = rawResponse.getJSONArray("artists");
					HashSet<String> artistsToAdd = new HashSet<>();
					HashSet<String> artistsWithRoleToAdd = new HashSet<>();
					for (int i = 0; i < artists.length(); i++) {
						JSONObject curArtist = artists.getJSONObject(i);
						String artistName = StringUtils.swapFirstLastNames(curArtist.getString("name"));
						artistsToAdd.add(artistName);
						artistsWithRoleToAdd.add(artistName + "|" + org.apache.commons.lang3.StringUtils.capitalize(curArtist.getString("relationship").toLowerCase()));
					}
					groupedWork.addAuthor2(artistsToAdd);
					groupedWork.addAuthor2Role(artistsWithRoleToAdd);
				}

				JSONArray genres = rawResponse.getJSONArray("genres");
				HashSet<String> genresToAdd = new HashSet<>();
				HashSet<String> topicsToAdd = new HashSet<>();
				for (int i = 0; i < genres.length(); i++) {
					String genre = genres.getString(i);

					genresToAdd.add(genre);
					topicsToAdd.add(genre);
				}
				groupedWork.addGenre(genresToAdd);
				groupedWork.addGenreFacet(genresToAdd);
				groupedWork.addTopicFacet(topicsToAdd);
				groupedWork.addTopic(topicsToAdd);

				HashMap<String, Integer> literaryForm = new HashMap<>();
				HashMap<String, Integer> literaryFormFull = new HashMap<>();
				if (rawResponse.has("fiction")){
					if (rawResponse.getBoolean("fiction") == true){
						Util.addToMapWithCount(literaryForm, "Fiction");
						Util.addToMapWithCount(literaryFormFull, "Fiction");
					}else{
						Util.addToMapWithCount(literaryForm, "Non Fiction");
						Util.addToMapWithCount(literaryFormFull, "Non Fiction");
					}
				}
				if (literaryForm.size() > 0){
					groupedWork.addLiteraryForms(literaryForm);
				}
				if (literaryFormFull.size() > 0){
					groupedWork.addLiteraryFormsFull(literaryFormFull);
				}

				String publisher = rawResponse.getString("publisher");
				groupedWork.addPublisher(publisher);
				//publication date
				Object yearObj = rawResponse.get("year");
				String releaseYear = yearObj.toString();

				groupedWork.addPublicationDate(releaseYear);
				//physical description
				if (rawResponse.has("duration")){
					groupedWork.addPhysical(rawResponse.getString("duration"));
				}

				//Description
				if (rawResponse.has("synopsis")) {
					String description = rawResponse.getString("synopsis");
					groupedWork.addDescription(description, primaryFormat);
				}

				String isbn = rawResponse.getString("isbn");
				groupedWork.addIsbn(isbn, primaryFormat);

				String upc = rawResponse.getString("upc");
				groupedWork.addUpc(upc);

				ItemInfo itemInfo = new ItemInfo();
				itemInfo.setItemIdentifier(identifier);
				itemInfo.seteContentSource("Hoopla");
				itemInfo.setIsEContent(true);
				itemInfo.seteContentUrl(rawResponse.getString("url"));
				itemInfo.setShelfLocation("Online Hoopla Collection");
				itemInfo.setDetailedLocation("Online Hoopla Collection");
				itemInfo.setCallNumber("Online Hoopla");
				itemInfo.setSortableCallNumber("Online Hoopla");
				itemInfo.setFormat(primaryFormat);
				itemInfo.setFormatCategory(formatCategory);
				//Hoopla is always 1 copy unlimited use
				itemInfo.setNumCopies(1);
				itemInfo.setAvailable(true);
				itemInfo.setDetailedStatus("Available Online");
				itemInfo.setGroupedStatus("Available Online");
				itemInfo.setHoldable(false);
				itemInfo.setInLibraryUseOnly(false);

				Date dateAdded = new Date(productRS.getLong("dateFirstDetected") * 1000);
				itemInfo.setDateAdded(dateAdded);

				boolean abridged = productRS.getBoolean("abridged");
				boolean pa = productRS.getBoolean("pa");
				boolean profanity = productRS.getBoolean("profanity");
				String rating = productRS.getString("rating");

				for (Scope scope : indexer.getScopes()) {
					boolean okToAdd = true;
					HooplaScope hooplaScope = scope.getHooplaScope();
					if (hooplaScope != null){
						//Filter by kind and price
						switch (kind){
							case "MOVIE":
								okToAdd = (hooplaScope.isIncludeMovies() && price <= hooplaScope.getMaxCostPerCheckoutMovies());
								break;
							case "TELEVISION":
								okToAdd = (hooplaScope.isIncludeTelevision() && price <= hooplaScope.getMaxCostPerCheckoutTelevision());
								break;
							case "AUDIOBOOK":
								okToAdd = (hooplaScope.isIncludeEAudiobook() && price <= hooplaScope.getMaxCostPerCheckoutEAudiobook());
								break;
							case "EBOOK":
								okToAdd = (hooplaScope.isIncludeEBooks() && price <= hooplaScope.getMaxCostPerCheckoutEBooks());
								break;
							case "COMIC":
								okToAdd = (hooplaScope.isIncludeEComics() && price <= hooplaScope.getMaxCostPerCheckoutEComics());
								break;
							case "MUSIC":
								okToAdd = (hooplaScope.isIncludeMusic() && price <= hooplaScope.getMaxCostPerCheckoutMusic());
								break;
							default:
								logger.error("Unknown kind " + kind);
						}
						if (okToAdd && hooplaScope.isExcludeAbridged() && abridged){
							okToAdd = false;
						}
						if (okToAdd && hooplaScope.isExcludeParentalAdvisory() && pa){
							okToAdd = false;
						}
						if (okToAdd && hooplaScope.isExcludeProfanity() && profanity){
							okToAdd = false;
						}
						if (okToAdd && hooplaScope.isRestrictToChildrensMaterial() && !children){
							okToAdd = false;
						}
						if (okToAdd && hooplaScope.isRatingExcluded(rating)){
							okToAdd = false;
						}
					}else{
						okToAdd = false;
					}
					if (okToAdd) {
						ScopingInfo scopingInfo = itemInfo.addScope(scope);
						scopingInfo.setLibraryOwned(true);
						scopingInfo.setLocallyOwned(true);
					}
				}

				hooplaRecord.addItem(itemInfo);

			}
			productRS.close();
		}catch (NullPointerException e) {
			logEntry.incErrors("Null pointer exception processing Hoopla record " + identifier + " grouped work " + groupedWork.getId(), e);
		} catch (JSONException e) {
			logEntry.incErrors("Error parsing raw data for Hoopla record " + identifier, e);
		} catch (SQLException e) {
			logEntry.incErrors("Error loading information from Database for Hoopla title " + identifier, e);
		}
	}

}
